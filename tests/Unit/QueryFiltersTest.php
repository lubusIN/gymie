<?php

use App\Models\Invoice;
use App\Services\Api\QueryFilters;
use Illuminate\Http\Request;

it('normalizes API pagination limits', function (mixed $value, int $expected): void {
    $query = $value === null ? [] : ['per_page' => $value];
    $request = Request::create('/api/v1/plans', 'GET', $query);

    expect(QueryFilters::perPage($request))->toBe($expected);
})->with([
    'missing' => [null, 15],
    'positive' => ['25', 25],
    'zero' => ['0', 15],
    'negative' => ['-1', 15],
    'not numeric' => ['invalid', 15],
    'above maximum' => ['250', 100],
]);

it('uses direct comparisons for indexed date-only filters', function (): void {
    $request = Request::create('/api/v1/invoices', 'GET', [
        'filter' => ['date' => '2026-09-22'],
    ]);
    $query = Invoice::query();

    QueryFilters::applyIndexFilters($query, $request, 'invoices');

    expect($query->toSql())
        ->toContain('"date" = ?')
        ->not->toContain('strftime');
});
