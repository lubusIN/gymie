<?php

namespace App\Filament\Resources\StaffAttendances\Pages;

use App\Enums\Status;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\StaffAttendances\StaffAttendanceResource;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\AppConfig;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListStaffAttendances extends ListRecords
{
    protected static string $resource = StaffAttendanceResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('check_in_staff')
                ->label(__('app.actions.check_in_staff'))
                ->icon('heroicon-o-arrow-left-end-on-rectangle')
                ->color('primary')
                ->authorize(fn (): bool => StaffAttendanceResource::canCreate())
                ->schema([
                    Select::make('staff_id')
                        ->label(__('app.staff_attendance.staff_member'))
                        ->options(fn (): array => $this->staffOptions())
                        ->getSearchResultsUsing(fn (?string $search): array => $this->staffOptions($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()
                            ->where('status', Status::Active->value)
                            ->find($value)?->name)
                        ->searchable()
                        ->preload()
                        ->required(),
                    DateTimePicker::make('checked_in_at')
                        ->label(__('app.fields.checked_in_at'))
                        ->default(fn (): string => now()->timezone(AppConfig::timezone())->format('Y-m-d H:i:s'))
                        ->seconds(false)
                        ->timezone(AppConfig::timezone())
                        ->maxDate(now()->timezone(AppConfig::timezone()))
                        ->required(),
                ])
                ->modalHeading(__('app.actions.check_in_staff'))
                ->modalSubmitActionLabel(__('app.actions.check_in'))
                ->modalWidth('sm')
                ->action(function (array $data, AttendanceService $service, Action $action): void {
                    $recordedBy = auth()->user();
                    assert($recordedBy instanceof User);

                    try {
                        $service->checkInStaff(
                            User::query()->findOrFail($data['staff_id']),
                            $recordedBy,
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
                        ->title(__('app.notifications.staff_checked_in'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTitle(): string
    {
        return AttendanceResource::getPluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<NavigationItem> */
    public function getSubNavigation(): array
    {
        return AttendanceResource::getWorkspaceNavigationItems();
    }

    /** @return array<int|string, string> */
    private function staffOptions(?string $search = null): array
    {
        $search = trim((string) $search);

        return User::query()
            ->where('status', Status::Active->value)
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }
}
