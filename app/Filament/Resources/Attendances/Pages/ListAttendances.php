<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Enums\Status;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Widgets\AttendanceOverviewWidget;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\AppConfig;
use chillerlan\QRCode\QRCode;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Attendance workspace for staff check-ins, history, and the shared QR code.
 */
class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    /** @var array<string, Subscription|null> */
    private array $selectedSubscriptions = [];

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('attendance_qr')
                ->label(__('app.actions.attendance_qr'))
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->authorize(fn (): bool => AttendanceResource::canCreate())
                ->modalHeading(__('app.actions.attendance_qr'))
                ->modalWidth('sm')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalContent(function (): View {
                    $url = $this->publicCheckInUrl();

                    return view('filament.resources.attendances.attendance-qr', [
                        'qrCode' => $this->renderQrCode($url),
                        'url' => $url,
                    ]);
                }),
            Action::make('check_in')
                ->label(__('app.actions.check_in'))
                ->icon('heroicon-o-arrow-left-end-on-rectangle')
                ->color('primary')
                ->authorize(fn (): bool => AttendanceResource::canCreate())
                ->schema([
                    Select::make('member_id')
                        ->label(__('app.resources.members.singular'))
                        ->options(fn (): array => $this->memberOptions())
                        ->getSearchResultsUsing(fn (?string $search): array => $this->memberOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => $this->memberOptionLabel(
                            Member::query()
                                ->where('status', Status::Active->value)
                                ->find($value),
                        ))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    TextEntry::make('membership_status')
                        ->label(__('app.fields.membership_status'))
                        ->state(fn (Get $get): string => $this->membershipSummary(
                            $get('member_id'),
                            $get('checked_in_at'),
                        ))
                        ->badge()
                        ->color(fn (Get $get): string => $this->membershipStatusColor(
                            $get('member_id'),
                            $get('checked_in_at'),
                        ))
                        ->icon('heroicon-o-ticket'),
                    DateTimePicker::make('checked_in_at')
                        ->label(__('app.fields.checked_in_at'))
                        ->default(fn (): string => now()->timezone(AppConfig::timezone())->format('Y-m-d H:i:s'))
                        ->seconds(false)
                        ->timezone(AppConfig::timezone())
                        ->maxDate(now()->timezone(AppConfig::timezone()))
                        ->live()
                        ->required(),
                ])
                ->fillForm(fn (array $arguments): array => [
                    'member_id' => $arguments['member_id'] ?? null,
                    'checked_in_at' => now()->timezone(AppConfig::timezone())->format('Y-m-d H:i:s'),
                ])
                ->modalHeading(__('app.actions.check_in_member'))
                ->modalSubmitActionLabel(__('app.actions.check_in'))
                ->modalWidth('sm')
                ->action(function (array $data, AttendanceService $service, Action $action): void {
                    $user = auth()->user();
                    assert($user instanceof User);

                    try {
                        $service->checkIn(
                            Member::query()->findOrFail($data['member_id']),
                            $user,
                            Carbon::parse($data['checked_in_at'], AppConfig::timezone()),
                        );
                    } catch (DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                        $action->halt();

                        return;
                    }

                    Notification::make()
                        ->title(__('app.notifications.member_checked_in'))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AttendanceOverviewWidget::class,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    private function membershipSummary(mixed $memberId, mixed $checkedInAt): string
    {
        if (! is_numeric($memberId)) {
            return __('app.placeholders.select_member');
        }

        $subscription = $this->selectedSubscription($memberId, $checkedInAt);

        if ($subscription === null) {
            return __('app.attendance.no_current_membership');
        }

        return __('app.attendance.membership_summary', [
            'plan' => $subscription->plan?->name ?? __('app.placeholders.na'),
            'date' => $subscription->end_date->format('d M Y'),
        ]);
    }

    /**
     * Load a bounded list of active members for the manual check-in selector.
     *
     * Membership validity is intentionally not part of this query so staff can
     * identify why an active member cannot check in.
     *
     * @return array<int|string, string>
     */
    private function memberOptions(?string $search = null): array
    {
        $search = trim((string) $search);

        return Member::query()
            ->where('status', Status::Active->value)
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Member $member): array => [
                $member->getKey() => $this->memberOptionLabel($member),
            ])
            ->all();
    }

    private function memberOptionLabel(?Member $member): ?string
    {
        return $member === null
            ? null
            : "{$member->code} · {$member->name}";
    }

    private function membershipStatusColor(mixed $memberId, mixed $checkedInAt): string
    {
        if (! is_numeric($memberId)) {
            return 'gray';
        }

        return $this->selectedSubscription($memberId, $checkedInAt) === null
            ? 'danger'
            : 'success';
    }

    private function selectedSubscription(mixed $memberId, mixed $checkedInAt): ?Subscription
    {
        if (! is_numeric($memberId)) {
            return null;
        }

        $date = filled($checkedInAt)
            ? Carbon::parse((string) $checkedInAt, AppConfig::timezone())
            : Carbon::now(AppConfig::timezone());

        $cacheKey = (int) $memberId.'|'.$date->toDateString();

        if (! array_key_exists($cacheKey, $this->selectedSubscriptions)) {
            $this->selectedSubscriptions[$cacheKey] = Member::query()
                ->find($memberId)?->currentSubscriptionAt($date);
        }

        return $this->selectedSubscriptions[$cacheKey];
    }

    /**
     * Build a host-independent signed path on the configured public application URL.
     */
    private function publicCheckInUrl(): string
    {
        $path = URL::signedRoute('attendance.check-in.create', absolute: false);

        return rtrim(AppConfig::string('app.url'), '/').$path;
    }

    private function renderQrCode(string $url): string
    {
        $qrCode = (new QRCode)->render($url);

        if (! is_string($qrCode)) {
            throw new RuntimeException('Unable to render the attendance QR code.');
        }

        return $qrCode;
    }
}
