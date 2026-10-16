<?php

namespace App\Support\Analytics;

use App\Support\AppConfig;
use Carbon\CarbonImmutable;

/**
 * A timezone-aware analytics date range derived from dashboard filters.
 *
 * Widgets receive raw filter data via `$this->pageFilters`. This value object
 * normalizes that data into an inclusive start/end range.
 */
final readonly class AnalyticsDateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Create a normalized date range from raw page filters.
     *
     * Supported keys:
     * - `period`: `today|7days|30days|month|quarter|year|custom`
     * - `startDate`: `Y-m-d`
     * - `endDate`: `Y-m-d`
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters, ?string $timezone = null): self
    {
        $filters ??= [];
        $timezone ??= AppConfig::timezone();
        $today = CarbonImmutable::today($timezone);

        $period = is_string($filters['period'] ?? null) ? $filters['period'] : '7days';
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;

        if ($period !== 'custom') {
            [$start, $end] = match ($period) {
                'today' => [$today, $today],
                '7days' => [$today->subDays(6), $today],
                '30days' => [$today->subDays(29), $today],
                'month' => [$today->startOfMonth(), $today],
                'quarter' => [$today->startOfQuarter(), $today],
                'ytd', 'year' => [$today->startOfYear(), $today],
                default => [$today->subDays(6), $today],
            };

            return new self($start->startOfDay(), $end->endOfDay());
        }

        $start = is_string($startDate) ? CarbonImmutable::parse($startDate, $timezone) : $today->startOfMonth();
        $end = is_string($endDate) ? CarbonImmutable::parse($endDate, $timezone) : $today;

        $start = $start->startOfDay();
        $end = $end->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return new self($start, $end);
    }

    /**
     * Get the reference date (end of range) as a date string for snapshot metrics.
     */
    public function referenceDateString(): string
    {
        return $this->end->toDateString();
    }

    /**
     * Return the immediately preceding range with the same inclusive day count.
     */
    public function previous(): self
    {
        $days = (int) $this->start
            ->startOfDay()
            ->diffInDays($this->end->startOfDay()) + 1;

        return new self(
            $this->start->subDays($days),
            $this->end->subDays($days),
        );
    }
}
