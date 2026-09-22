<?php

namespace App\Services\Analytics;

use App\Helpers\Helpers;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\AppConfig;
use App\Support\Data;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Analytics query service for dashboards and reports.
 *
 * Metrics are "collected-first": revenue is derived from the payment ledger
 * (`invoice_transactions`) rather than invoice statuses.
 */
class AnalyticsService
{
    /**
     * Build a SQL expression that groups a date/datetime column by month.
     */
    private function monthGroupExpression(string $column, string $driver): string
    {
        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /**
     * Financial metrics for the given date range.
     *
     * Definitions:
     * - `net_revenue`: total invoiced sales - refunds (sales based on invoice dates)
     * - `collected`: payments - refunds (from transactions in range)
     * - `refunds`: refund transactions total (in range)
     * - `discounts`: invoice discount_amount total (invoice date in range)
     * - `outstanding`: sum of invoice due_amount as-of the range end date
     * - `expenses`: sum of expenses (expense date in range)
     * - `profit`: collected - expenses
     *
     * @return array{net_revenue: float, collected: float, refunds: float, discounts: float, outstanding: float, expenses: float, profit: float}
     */
    public function financialMetrics(AnalyticsDateRange $range): array
    {
        $salesTotal = (float) Invoice::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('total_amount');

        $transactions = InvoiceTransaction::query()
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw("SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments_total")
            ->selectRaw("SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as refunds_total")
            ->first();

        $paymentsTotal = (float) ($transactions->payments_total ?? 0);
        $refundsTotal = (float) ($transactions->refunds_total ?? 0);
        $collected = max($paymentsTotal - $refundsTotal, 0);
        $netRevenue = max($salesTotal - $refundsTotal, 0);

        $discounts = (float) Invoice::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('discount_amount');

        $outstanding = (float) Invoice::query()
            ->where('date', '<=', $range->referenceDateString())
            ->where('due_amount', '>', 0)
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->sum('due_amount');

        $expenses = (float) Expense::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('amount');

        return [
            'net_revenue' => $netRevenue,
            'collected' => $collected,
            'refunds' => $refundsTotal,
            'discounts' => $discounts,
            'outstanding' => $outstanding,
            'expenses' => $expenses,
            'profit' => max($collected - $expenses, 0),
        ];
    }

    /**
     * Total revenue (invoiced) by date for the given range.
     *
     * @return array<string, float> Map of `Y-m-d` => amount
     */
    public function revenueTrendByDate(AnalyticsDateRange $range): array
    {
        /** @var Collection<string, float> $rows */
        $rows = Invoice::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->selectRaw('date as day')
            ->selectRaw('SUM(total_amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($value): float => Data::float($value));

        /** @var array<string, float> $result */
        $result = $rows->all();

        return $result;
    }

    /**
     * Membership metrics for the given date range.
     *
     * @return array{active_members: int, new_signups: int, renewals: int, expired_not_renewed: int}
     */
    public function membershipMetrics(AnalyticsDateRange $range): array
    {
        $referenceDate = $range->referenceDateString();

        $activeMembers = Member::query()
            ->whereHas('subscriptions', function (Builder $query) use ($referenceDate): void {
                $query
                    ->where('start_date', '<=', $referenceDate)
                    ->where('end_date', '>=', $referenceDate);
            })
            ->count();

        $newSignups = Member::query()
            ->whereBetween('created_at', [$range->start, $range->end])
            ->count();

        $renewals = Subscription::query()
            ->whereNotNull('renewed_from_subscription_id')
            ->whereBetween('start_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->count();

        $expiredNotRenewed = Subscription::query()
            ->whereBetween('end_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->whereDoesntHave('renewals')
            ->count();

        return [
            'active_members' => $activeMembers,
            'new_signups' => $newSignups,
            'renewals' => $renewals,
            'expired_not_renewed' => $expiredNotRenewed,
        ];
    }

    /**
     * Count subscriptions that are expiring within the configured window.
     *
     * This is based on dates (not status), so it works even if status syncing
     * hasn't run yet.
     */
    public function expiringSubscriptionsCount(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today(AppConfig::timezone());
        $expiringDays = Helpers::getSubscriptionExpiringDays();
        $end = $today->addDays($expiringDays);

        return Subscription::query()
            ->where('start_date', '<=', $today->toDateString())
            ->whereBetween('end_date', [$today->toDateString(), $end->toDateString()])
            ->count();
    }

    /**
     * Count invoices that are currently overdue and still have due amount.
     */
    public function overdueInvoicesCount(): int
    {
        return Invoice::query()
            ->where('status', 'overdue')
            ->where('due_amount', '>', 0)
            ->count();
    }

    /**
     * Net collected by date for the given range (payments - refunds).
     *
     * @return array<string, float> Map of `Y-m-d` => amount
     */
    public function collectedTrendByDate(AnalyticsDateRange $range): array
    {
        /** @var Collection<string, float> $rows */
        $rows = InvoiceTransaction::query()
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw('DATE(occurred_at) as day')
            ->selectRaw("SUM(CASE WHEN type = 'payment' THEN amount WHEN type = 'refund' THEN -amount ELSE 0 END) as net")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('net', 'day')
            ->map(fn ($value): float => Data::float($value));

        /** @var array<string, float> $result */
        $result = $rows->all();

        return $result;
    }

    /**
     * Net collected by month for the given range (payments - refunds).
     *
     * @return array<string, float> Map of `Y-m` => amount
     */
    public function collectedTrendByMonth(AnalyticsDateRange $range): array
    {
        $driver = InvoiceTransaction::query()->getModel()->getConnection()->getDriverName();
        $monthExpression = $this->monthGroupExpression('occurred_at', $driver);

        /** @var Collection<string, float> $rows */
        $rows = InvoiceTransaction::query()
            ->whereBetween('occurred_at', [$range->start, $range->end])
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw("SUM(CASE WHEN type = 'payment' THEN amount WHEN type = 'refund' THEN -amount ELSE 0 END) as net")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('net', 'month')
            ->map(fn ($value): float => Data::float($value));

        /** @var array<string, float> $result */
        $result = $rows->all();

        return $result;
    }

    /**
     * Expense totals by date for the given range.
     *
     * @return array<string, float> Map of `Y-m-d` => amount
     */
    public function expenseTrendByDate(AnalyticsDateRange $range): array
    {
        /** @var Collection<string, float> $rows */
        $rows = Expense::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->selectRaw('date as day')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn ($value): float => Data::float($value));

        /** @var array<string, float> $result */
        $result = $rows->all();

        return $result;
    }

    /**
     * Expense totals by month for the given range.
     *
     * @return array<string, float> Map of `Y-m` => amount
     */
    public function expenseTrendByMonth(AnalyticsDateRange $range): array
    {
        $driver = Expense::query()->getModel()->getConnection()->getDriverName();
        $monthExpression = $this->monthGroupExpression('date', $driver);

        /** @var Collection<string, float> $rows */
        $rows = Expense::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw('SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->map(fn ($value): float => Data::float($value));

        /** @var array<string, float> $result */
        $result = $rows->all();

        return $result;
    }

    /**
     * Top plans by collected amount in the given range.
     *
     * Note: Table widgets that use array records require a unique `key` entry.
     *
     * @return Collection<int, array{key: string, plan_id: int, plan_name: string, collected: float, subscriptions: int}>
     */
    public function topPlansByCollected(AnalyticsDateRange $range, int $limit = 5): Collection
    {
        /** @var Collection<int, object{plan_id:int, plan_name:string, collected: float, subscriptions:int}> $rows */
        $rows = Plan::query()
            ->select('plans.id as plan_id', 'plans.name as plan_name')
            ->leftJoin('subscriptions', 'subscriptions.plan_id', '=', 'plans.id')
            ->leftJoin('invoices', 'invoices.subscription_id', '=', 'subscriptions.id')
            ->leftJoin('invoice_transactions', function ($join) use ($range): void {
                $join
                    ->on('invoice_transactions.invoice_id', '=', 'invoices.id')
                    ->whereBetween('invoice_transactions.occurred_at', [$range->start, $range->end]);
            })
            ->selectRaw("COALESCE(SUM(CASE WHEN invoice_transactions.type = 'payment' THEN invoice_transactions.amount WHEN invoice_transactions.type = 'refund' THEN -invoice_transactions.amount ELSE 0 END), 0) as collected")
            ->selectRaw('COUNT(DISTINCT subscriptions.id) as subscriptions')
            ->groupBy('plans.id', 'plans.name')
            ->orderByDesc('collected')
            ->limit($limit)
            ->get();

        return $rows->map(function (object $row): array {
            return [
                'key' => 'plan:'.(int) $row->plan_id,
                'plan_id' => (int) $row->plan_id,
                'plan_name' => (string) $row->plan_name,
                'collected' => (float) $row->collected,
                'subscriptions' => (int) $row->subscriptions,
            ];
        });
    }

    /**
     * Expense totals by category for the given range.
     *
     * Note: Table widgets that use array records require a unique `key` entry.
     *
     * @return Collection<int, array{key: string, category: string, total: float}>
     */
    public function expenseBreakdownByCategory(AnalyticsDateRange $range, int $limit = 10): Collection
    {
        /** @var Collection<int, object{category: string, total: float}> $rows */
        $rows = Expense::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->select('category')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return $rows->map(function (object $row): array {
            return [
                'key' => 'expense-category:'.(string) $row->category,
                'category' => (string) $row->category,
                'total' => (float) $row->total,
            ];
        });
    }

    /**
     * Expense category breakdown for charts with an optional "Other" bucket.
     *
     * @return Collection<int, array{category: string, total: float}>
     */
    public function expenseCategoryBreakdownForChart(AnalyticsDateRange $range, int $limit = 5): Collection
    {
        /** @var Collection<int, array{category: string, total: float}> $rows */
        $top = $this->expenseBreakdownByCategory($range, $limit)
            ->map(fn (array $row): array => [
                'category' => (string) $row['category'],
                'total' => (float) $row['total'],
            ])
            ->values();

        $allCategoriesTotal = Data::float(Expense::query()
            ->whereBetween('date', [$range->start->toDateString(), $range->end->toDateString()])
            ->sum('amount'));
        $otherTotal = round(max($allCategoriesTotal - Data::float($top->sum('total')), 0), 2);

        if ($otherTotal <= 0) {
            return $top;
        }

        return $top->push([
            'category' => 'Other',
            'total' => $otherTotal,
        ]);
    }
}
