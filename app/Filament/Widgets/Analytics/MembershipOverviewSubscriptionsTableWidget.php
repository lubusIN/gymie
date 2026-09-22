<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Resources\Subscriptions\Schemas\SubscriptionForm;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Helpers\Helpers;
use App\Models\Subscription;
use App\Support\AppConfig;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Dashboard widget to monitor memberships that need attention.
 *
 * This widget is date-driven (not status-driven) so it stays accurate even if
 * the scheduled status-sync command hasn't run yet.
 */
class MembershipOverviewSubscriptionsTableWidget extends TableWidget
{
    protected static ?int $sort = -39;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
    ];

    /**
     * Active tab key.
     *
     * @var 'expiring'|'expired'
     */
    public string $activeTab = 'expiring';

    /**
     * Reset pagination whenever the active tab changes.
     */
    public function updatedActiveTab(string $activeTab): void
    {
        if (! in_array($activeTab, ['expiring', 'expired'], true)) {
            $this->activeTab = 'expiring';

            return;
        }

        $this->resetPage();
    }

    /**
     * Render the table header including expiring/expired toggles.
     */
    private function tableHeader(): HtmlString
    {
        return new HtmlString(Blade::render(
            <<<'BLADE'
<div class="fi-ta-header flex flex-row items-center justify-between">
    <div>
        <h3 class="fi-ta-header-heading">
            {{ $title }}
        </h3>
    </div>

    <div class="fi-ta-actions fi-align-end fi-wrapped">
        <x-filament::button.group>
            <x-filament::button
                size="sm"
                :color="$activeTab === 'expiring' ? 'primary' : 'gray'"
                :outlined="$activeTab !== 'expiring'"
                wire:click="$set('activeTab', 'expiring')"
            >
                {{ $expiringLabel }}
            </x-filament::button>

            <x-filament::button
                size="sm"
                :color="$activeTab === 'expired' ? 'primary' : 'gray'"
                :outlined="$activeTab !== 'expired'"
                wire:click="$set('activeTab', 'expired')"
            >
                {{ $expiredLabel }}
            </x-filament::button>
        </x-filament::button.group>
    </div>
</div>
BLADE,
            [
                'activeTab' => $this->activeTab,
                'title' => __('app.navigation.groups.memberships'),
                'expiringLabel' => __('app.status.expiring'),
                'expiredLabel' => __('app.status.expired'),
            ],
        ));
    }

    /**
     * Build the query for the currently selected tab.
     *
     * @return Builder<Subscription>
     */
    protected function getActiveTabQuery(): Builder
    {
        return match ($this->activeTab) {
            'expired' => $this->getExpiredQuery(),
            default => $this->getExpiringSoonQuery(),
        };
    }

    /**
     * Query memberships that are expiring within the configured window.
     *
     * @return Builder<Subscription>
     */
    protected function getExpiringSoonQuery(): Builder
    {
        $today = CarbonImmutable::today(AppConfig::timezone());
        $end = $today->addDays(Helpers::getSubscriptionExpiringDays());

        return Subscription::query()
            ->with(['member', 'plan'])
            ->where('start_date', '<=', $today->toDateString())
            ->whereBetween('end_date', [$today->toDateString(), $end->toDateString()])
            ->orderBy('end_date');
    }

    /**
     * Query memberships that are already expired.
     *
     * @return Builder<Subscription>
     */
    protected function getExpiredQuery(): Builder
    {
        $today = CarbonImmutable::today(AppConfig::timezone());

        return Subscription::query()
            ->with(['member', 'plan'])
            ->where('end_date', '<', $today->toDateString())
            ->orderByDesc('end_date');
    }

    public function table(Table $table): Table
    {
        $today = CarbonImmutable::today(AppConfig::timezone())->toDateString();

        return $table
            ->heading(null)
            ->header(fn (): HtmlString => $this->tableHeader())
            ->paginated(false)
            ->query(fn (): Builder => $this->getActiveTabQuery()->limit(5))
            ->columns([
                TextColumn::make('member.name')
                    ->label(__('app.fields.member'))
                    ->description(fn (Subscription $record): string => (string) ($record->member->code ?? ''))
                    ->wrap(),
                TextColumn::make('plan.name')
                    ->label(__('app.fields.plan'))
                    ->description(fn (Subscription $record): string => (string) ($record->plan->code ?? ''))
                    ->wrap(),
                TextColumn::make('end_date')
                    ->label(__('app.fields.end_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('days')
                    ->label(fn (): string => $this->activeTab === 'expired' ? __('app.widgets.days_since') : __('app.widgets.days_left'))
                    ->alignRight()
                    ->state(function (Subscription $record): string {
                        $today = CarbonImmutable::today(AppConfig::timezone());
                        $endDate = CarbonImmutable::parse($record->end_date, AppConfig::timezone())->startOfDay();

                        if ($this->activeTab === 'expired') {
                            $days = max($endDate->diffInDays($today, false), 0);

                            $unit = (int) $days === 1 ? __('app.units.day') : __('app.units.days');

                            return "{$days} {$unit}";
                        }

                        $days = max($today->diffInDays($endDate, false), 0);

                        $unit = (int) $days === 1 ? __('app.units.day') : __('app.units.days');

                        return "{$days} {$unit}";
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('renew')
                        ->label(__('app.actions.renew'))
                        ->icon('heroicon-m-arrow-path')
                        ->color('success')
                        ->modalHeading(__('app.titles.renew_subscription'))
                        ->modalSubmitActionLabel(__('app.actions.renew'))
                        ->modalWidth('6xl')
                        ->closeModalByClickingAway(false)
                        ->visible(function (Subscription $record) use ($today): bool {
                            if ($record->renewals()->exists()) {
                                return false;
                            }

                            return ! Subscription::query()
                                ->where('member_id', $record->member_id)
                                ->where('start_date', '>', $today)
                                ->exists();
                        })
                        ->schema(fn (Subscription $record): array => SubscriptionForm::renewSchema($record))
                        ->action(function (Subscription $record, array $data): void {
                            SubscriptionForm::handleRenew($record, $data);
                        }),
                    ViewAction::make()
                        ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),
                ]),
            ])
            ->emptyStateHeading(fn (): string => $this->activeTab === 'expired'
                ? __('app.widgets.no_expired_memberships')
                : __('app.widgets.no_expiring_memberships'))
            ->emptyStateDescription(fn (): string => $this->activeTab === 'expired'
                ? __('app.widgets.no_expired_memberships_description')
                : __('app.widgets.no_expiring_memberships_description'))
            ->defaultPaginationPageOption(5);
    }
}
