<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\InvoiceTransactionResource;
use App\Models\InvoiceTransaction;
use App\Services\Analytics\AnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only analytics endpoints for dashboards / reports.
 */
class AnalyticsController extends ApiController
{
    public function __construct(private readonly AnalyticsService $analyticsService) {}

    /**
     * Get financial KPIs for a range.
     */
    public function financial(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'ViewAny:Analytics');

        $range = $this->dateRange($request);
        $metrics = $this->analyticsService->financialMetrics($range);

        return response()->json([
            'data' => [
                'range' => [
                    'start' => $range->start->toDateString(),
                    'end' => $range->end->toDateString(),
                ],
                'metrics' => $metrics,
            ],
        ]);
    }

    /**
     * Get membership KPIs for a range.
     */
    public function membership(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'ViewAny:Analytics');

        $range = $this->dateRange($request);
        $metrics = $this->analyticsService->membershipMetrics($range);

        return response()->json([
            'data' => [
                'range' => [
                    'start' => $range->start->toDateString(),
                    'end' => $range->end->toDateString(),
                ],
                'metrics' => $metrics,
            ],
        ]);
    }

    /**
     * Cashflow trend (collected vs expenses).
     */
    public function cashflowTrend(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'ViewAny:Analytics');

        $range = $this->dateRange($request);

        $days = $range->end->diffInDays($range->start) + 1;
        $grouping = $days <= 31 ? 'day' : 'month';

        $collected = $grouping === 'day'
            ? $this->analyticsService->collectedTrendByDate($range)
            : $this->analyticsService->collectedTrendByMonth($range);

        $expenses = $grouping === 'day'
            ? $this->analyticsService->expenseTrendByDate($range)
            : $this->analyticsService->expenseTrendByMonth($range);

        $labels = collect(array_keys($collected))
            ->merge(array_keys($expenses))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $collectedSeries = [];
        $expenseSeries = [];

        foreach ($labels as $label) {
            $collectedSeries[] = (float) ($collected[$label] ?? 0);
            $expenseSeries[] = (float) ($expenses[$label] ?? 0);
        }

        return response()->json([
            'data' => [
                'grouping' => $grouping,
                'labels' => $labels,
                'series' => [
                    'collected' => $collectedSeries,
                    'expenses' => $expenseSeries,
                ],
            ],
        ]);
    }

    /**
     * Expense breakdown by category for a range.
     */
    public function expenseCategories(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'ViewAny:Analytics');

        $range = $this->dateRange($request);
        $rows = $this->analyticsService->expenseBreakdownByCategory($range, 10);

        return response()->json([
            'data' => $rows->values()->all(),
        ]);
    }

    /**
     * Top plans by collected amount.
     */
    public function topPlans(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'ViewAny:Analytics');

        $range = $this->dateRange($request);
        $rows = $this->analyticsService->topPlansByCollected($range, 5);

        return response()->json([
            'data' => $rows->values()->all(),
        ]);
    }

    /**
     * Recent invoice transactions (payments/refunds).
     */
    public function recentTransactions(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Analytics');

        $limit = $request->integer('limit', 5);
        $limit = $limit > 0 ? min($limit, 50) : 5;

        $rows = InvoiceTransaction::query()
            ->with([
                'invoice.subscription.member',
                'invoice.subscription.plan',
            ])
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        return InvoiceTransactionResource::collection($rows);
    }

    /**
     * Build a date range from the supported analytics filters only.
     */
    private function dateRange(Request $request): AnalyticsDateRange
    {
        return AnalyticsDateRange::fromFilters($request->only([
            'period',
            'startDate',
            'endDate',
        ]));
    }
}
