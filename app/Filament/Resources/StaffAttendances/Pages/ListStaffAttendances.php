<?php

namespace App\Filament\Resources\StaffAttendances\Pages;

use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\StaffAttendances\StaffAttendanceResource;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\AppConfig;
use App\Support\Filament\HasDateRangeFilters;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListStaffAttendances extends ListRecordsWithTableTabs
{
    use HasDateRangeFilters;
    use HasFilters;

    protected static string $resource = StaffAttendanceResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->initializeDateRangeFilters();
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.date-range-header', [
            'page' => $this,
            'dateRangeForm' => $this->dateRangeForm,
        ]);
    }

    public function dateRangeForm(Schema $schema): Schema
    {
        return $this->configureDateRangeFilters($schema);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(
            fn (Builder $query): Builder => $this->applyDateRangeToQuery($query, 'checked_in_at'),
        );
    }

    protected function defaultDateRangePeriod(): string
    {
        return 'today';
    }

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
                ->modalWidth(Width::Small)
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

    public function getTabs(): array
    {
        return AttendanceResource::getWorkspaceTabs();
    }

    public function getDefaultActiveTab(): string
    {
        return 'staff';
    }

    protected function getTableTabUrl(string $tab): ?string
    {
        return AttendanceResource::getWorkspaceTabUrl($tab);
    }

    protected function supportsAllDateRange(): bool
    {
        return true;
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
