<?php

namespace App\Filament\Widgets;

use App\Enums\Status;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Support\AppConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ExpenseOverviewWidget extends StatsOverviewWidget
{
    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var int | array<string, ?int> | null
     */
    protected int|array|null $columns = 3;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $today = Carbon::today(AppConfig::timezone());

        $paidThisMonth = Expense::query()
            ->where('status', Status::Paid->value)
            ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $today->copy()->endOfDay()])
            ->sum('amount');

        $pending = Expense::query()
            ->where('status', Status::Pending->value)
            ->sum('amount');

        $overdue = Expense::query()
            ->where('status', Status::Overdue->value)
            ->sum('amount');

        return [
            Stat::make(__('app.widgets.paid_expenses_this_month'), Helpers::formatCurrency($paidThisMonth))
                ->color('success'),
            Stat::make(__('app.widgets.pending_expenses'), Helpers::formatCurrency($pending))
                ->color('warning'),
            Stat::make(__('app.widgets.overdue_expenses'), Helpers::formatCurrency($overdue))
                ->color('danger'),
        ];
    }
}
