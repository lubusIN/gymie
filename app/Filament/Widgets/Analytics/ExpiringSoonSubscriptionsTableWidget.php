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

/**
 * Dashboard widget listing subscriptions that are expiring soon.
 *
 * This is date-driven (not status-driven) so it stays accurate even if the
 * status sync command hasn't run yet.
 */
class ExpiringSoonSubscriptionsTableWidget extends TableWidget
{
    protected static ?int $sort = -39;

    protected static ?string $heading = null;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 3,
    ];

    /**
     * Build the query that powers the expiring soon table.
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

    private function formatDayCount(int $days): string
    {
        $unit = $days === 1 ? __('app.units.day') : __('app.units.days');

        return "{$days} {$unit}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.widgets.expiring_soon'))
            ->query(fn (): Builder => $this->getExpiringSoonQuery())
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
                TextColumn::make('days_left')
                    ->label(__('app.widgets.days_left'))
                    ->alignRight()
                    ->state(function (Subscription $record): string {
                        $today = CarbonImmutable::today(AppConfig::timezone());
                        $endDate = CarbonImmutable::parse($record->end_date, AppConfig::timezone())->startOfDay();

                        $days = (int) max($today->diffInDays($endDate, false), 0);

                        return $this->formatDayCount($days);
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
                        ->visible(function (Subscription $record): bool {
                            if ($record->renewals()->exists()) {
                                return false;
                            }

                            $today = CarbonImmutable::today(AppConfig::timezone())->toDateString();

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
            ->emptyStateHeading(__('app.widgets.no_expiring_subscriptions'))
            ->emptyStateDescription(__('app.widgets.no_expiring_subscriptions_description'))
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50]);
    }
}
