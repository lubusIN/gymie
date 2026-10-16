<?php

use App\Support\Analytics\AnalyticsDateRange;
use Carbon\CarbonImmutable;

it('resolves today correctly', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23 14:30:00', 'UTC'));

    $range = AnalyticsDateRange::fromFilters([
        'period' => 'today',
    ]);

    expect($range->start->toDateTimeString())->toBe('2026-02-23 00:00:00')
        ->and($range->end->toDateTimeString())->toBe('2026-02-23 23:59:59');
});

it('resolves last 30 days correctly', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    $range = AnalyticsDateRange::fromFilters([
        'period' => '30days',
    ]);

    expect($range->start->toDateString())->toBe('2026-01-25');
    expect($range->end->toDateString())->toBe('2026-02-23');
});

it('resolves month to date correctly', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    $range = AnalyticsDateRange::fromFilters([
        'period' => 'month',
    ]);

    expect($range->start->toDateString())->toBe('2026-02-01');
    expect($range->end->toDateString())->toBe('2026-02-23');
});

it('swaps custom range when dates are reversed', function (): void {
    config()->set('app.timezone', 'UTC');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-23', 'UTC'));

    $range = AnalyticsDateRange::fromFilters([
        'period' => 'custom',
        'startDate' => '2026-02-20',
        'endDate' => '2026-02-10',
    ]);

    expect($range->start->toDateString())->toBe('2026-02-10');
    expect($range->end->toDateString())->toBe('2026-02-20');
});

it('returns the immediately preceding range with the same inclusive day count', function (): void {
    $range = new AnalyticsDateRange(
        CarbonImmutable::parse('2026-02-17 00:00:00', 'UTC'),
        CarbonImmutable::parse('2026-02-23 23:59:59', 'UTC'),
    );

    $previous = $range->previous();

    expect($previous->start->toDateTimeString())->toBe('2026-02-10 00:00:00')
        ->and($previous->end->toDateTimeString())->toBe('2026-02-16 23:59:59');
});
