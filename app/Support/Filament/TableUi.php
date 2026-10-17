<?php

namespace App\Support\Filament;

use App\Enums\Status;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class TableUi
{
    private function __construct() {}

    /**
     * @param  class-string<Model>  $model
     * @param  list<Status>  $statuses
     * @param  (Closure(Builder): Builder)|null  $scopeBadgeQuery
     * @return array<string, Tab>
     */
    public static function statusTabs(
        string $model,
        array $statuses,
        ?Closure $scopeBadgeQuery = null,
    ): array {
        $tabs = ['all' => Tab::make(__('app.common.all'))];
        $statusValues = array_map(
            fn (Status $status): string => $status->value,
            $statuses,
        );
        $badgeQuery = $model::query()->whereIn('status', $statusValues);

        if ($scopeBadgeQuery) {
            $badgeQuery = $scopeBadgeQuery($badgeQuery);
        }

        $badgeCounts = $badgeQuery
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach ($statuses as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge((int) ($badgeCounts[$status->value] ?? 0))
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(
                    fn (Builder $query): Builder => $query->where('status', $status->value),
                );
        }

        return $tabs;
    }

    public static function dateRangeFilter(string $column = 'created_at', ?string $endColumn = null): Filter
    {
        $endColumn ??= $column;

        return Filter::make('date')
            ->schema([
                DatePicker::make('date_from')->label(__('app.fields.date_from')),
                DatePicker::make('date_to')->label(__('app.fields.date_to')),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when(
                    $data['date_from'] ?? null,
                    fn (Builder $query, string $date): Builder => $query->whereDate($endColumn, '>=', $date),
                )
                ->when(
                    $data['date_to'] ?? null,
                    fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date),
                ));
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, string>  $statusLabels
     */
    public static function emptyStateHeading(
        HasTable $livewire,
        string $model,
        string $resource,
        array $statusLabels = [],
    ): string {
        [$from, $to] = self::dateRange($livewire);
        $tab = self::activeTab($livewire);
        $records = (string) __("app.resources.{$resource}.plural");
        $status = self::statusLabel($tab, $statusLabels);

        if (! $from && ! $to) {
            return $status
                ? __('app.empty.no_status_records', compact('status', 'records'))
                : __('app.empty.no_records', compact('records'));
        }

        if ($tab === 'all') {
            return __('app.empty.no_records_in_range', compact('records'));
        }

        return $model::query()->where('status', $tab)->exists()
            ? __('app.empty.no_status_records_in_range', compact('status', 'records'))
            : __('app.empty.no_status_records', compact('status', 'records'));
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, string>  $statusLabels
     */
    public static function emptyStateDescription(
        HasTable $livewire,
        string $model,
        string $resource,
        array $statusLabels = [],
    ): string {
        [$fromRaw, $toRaw] = self::dateRange($livewire);
        $tab = self::activeTab($livewire);
        $records = (string) __("app.resources.{$resource}.plural");
        $record = (string) __("app.resources.{$resource}.singular");
        $status = self::statusLabel($tab, $statusLabels);

        if (! $fromRaw && ! $toRaw) {
            return $status
                ? __('app.empty.no_records_marked_as', compact('records', 'status'))
                : __('app.empty.create_to_get_started', ['resource' => $record]);
        }

        $from = $fromRaw
            ? Carbon::parse($fromRaw)->format('d-m-Y')
            : (string) __('app.common.the_beginning');
        $to = $toRaw
            ? Carbon::parse($toRaw)->format('d-m-Y')
            : (string) __('app.common.today');

        if ($tab === 'all') {
            return __('app.empty.found_none_between', compact('records', 'from', 'to'));
        }

        if (! $model::query()->where('status', $tab)->exists()) {
            return __('app.empty.no_records_marked_as', compact('records', 'status'));
        }

        return __('app.empty.found_none_status_between', compact('status', 'records', 'from', 'to'));
    }

    /**
     * @param  list<Action|ActionGroup>  $primaryActions
     * @param  list<Action|ActionGroup>  $recordActions
     */
    public static function actionGroup(
        string $primaryLabel,
        array $primaryActions,
        array $recordActions,
        bool|Closure $primaryVisible = true,
    ): ActionGroup {
        return ActionGroup::make([
            ActionGroup::make([
                self::heading('primary_actions_heading', $primaryLabel),
                ...$primaryActions,
            ])
                ->dropdown(false)
                ->visible($primaryVisible),
            ActionGroup::make([
                self::heading('record_actions_heading', __('app.actions.record_actions')),
                ...$recordActions,
            ])->dropdown(false),
        ]);
    }

    private static function heading(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->disabled()
            ->color('gray');
    }

    /** @return array{mixed, mixed} */
    private static function dateRange(HasTable $livewire): array
    {
        $pageFilters = data_get($livewire, 'filters');

        if (is_array($pageFilters)) {
            $from = $pageFilters['startDate'] ?? null;
            $to = $pageFilters['endDate'] ?? null;

            if ($from || $to) {
                return [$from, $to];
            }
        }

        $dates = $livewire->getTableFilterState('date') ?? [];

        return [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
    }

    private static function activeTab(HasTable $livewire): string
    {
        $activeTab = data_get($livewire, 'activeTab');

        return is_string($activeTab) && filled($activeTab) ? $activeTab : 'all';
    }

    /** @param array<string, string> $statusLabels */
    private static function statusLabel(string $tab, array $statusLabels): ?string
    {
        if ($tab === 'all') {
            return null;
        }

        return $statusLabels[$tab] ?? Status::tryFrom($tab)?->getLabel() ?? (string) __("app.status.{$tab}");
    }
}
