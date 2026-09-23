<?php

namespace App\Services\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Small, reusable query filter helpers for API index endpoints.
 *
 * This intentionally stays "thin" so each controller can decide:
 * - which columns are searchable
 * - which sort keys are allowed
 * - whether a model supports soft deletes
 */
final class QueryFilters
{
    /**
     * Apply all common index filters for a resource using allowlisted rules.
     *
     * This consolidates controller boilerplate while keeping query rules explicit
     * (see {@see ResourceQueryRules}).
     */
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyIndexFilters(Builder $query, Request $request, string $resourceKey): Builder
    {
        self::validateIndexQueryParameters($request, $resourceKey);

        self::applyTrashed($query, $request->query('trashed'));
        self::applySearch($query, $request->query('q'), ResourceQueryRules::searchable($resourceKey));

        $includes = ResourceQueryRules::includes($resourceKey);
        $filters = ResourceQueryRules::filters($resourceKey);

        $statusColumn = ResourceQueryRules::statusColumn($resourceKey);
        if ($statusColumn !== null) {
            self::applyStatus($query, $request->query('status'), $statusColumn);
        }

        self::applyQueryBuilderFilters(
            $query,
            $request,
            $includes,
            $filters,
            ResourceQueryRules::sortable($resourceKey),
            ResourceQueryRules::defaultSort($resourceKey),
        );

        return $query;
    }

    private static function validateIndexQueryParameters(Request $request, string $resourceKey): void
    {
        $errors = [];

        $trashed = $request->query('trashed');
        if ($trashed !== null) {
            $trashed = trim((string) $trashed);
            if ($trashed !== '' && ! in_array($trashed, ['with', 'only'], true)) {
                $errors['trashed'][] = 'Allowed values are `with` or `only`.';
            }
        }

        $include = $request->query('include');
        if ($include !== null) {
            if (! is_string($include)) {
                $errors['include'][] = 'Include must be a comma-separated string.';
            }
        }

        $sort = $request->query('sort');
        if ($sort !== null) {
            if (! is_string($sort)) {
                $errors['sort'][] = 'Sort must be a comma-separated string.';
            }
        }

        $rules = ResourceQueryRules::filters($resourceKey);
        $filter = $request->query('filter');
        if ($filter !== null) {
            if (! is_array($filter)) {
                $errors['filter'][] = 'Filter must be an object (e.g. `filter[field]=value`).';
            } else {
                foreach ($filter as $key => $value) {
                    if (! is_string($key) || $key === '') {
                        $errors['filter'][] = 'Filter keys must be strings.';

                        continue;
                    }

                    if (! array_key_exists($key, $rules)) {
                        continue;
                    }

                    if (! is_scalar($value)) {
                        $errors["filter.{$key}"][] = 'Filter value must be a scalar.';

                        continue;
                    }

                    $value = trim((string) $value);
                    if ($value === '') {
                        continue;
                    }

                    $type = $rules[$key]['type'];
                    if (in_array($type, ['date_range', 'datetime_range'], true) && str_contains($value, '..') && self::parseRange($value) === null) {
                        $errors["filter.{$key}"][] = 'Range filters must use `from..to`.';
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new HttpResponseException(response()->json([
                'message' => __('app.api.invalid_query'),
                'errors' => $errors,
            ], 400));
        }
    }

    /**
     * Apply allowlisted filtering / includes / sorting using spatie/laravel-query-builder.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $allowedIncludes
     * @param  array<string, array{type: string, column: string}>  $filterRules
     * @param  list<string>  $allowedSorts
     */
    private static function applyQueryBuilderFilters(
        Builder $query,
        Request $request,
        array $allowedIncludes,
        array $filterRules,
        array $allowedSorts,
        string $defaultSort,
    ): void {
        /** @var list<AllowedFilter> $allowedFilters */
        $allowedFilters = [];
        foreach ($filterRules as $key => $rule) {
            $type = $rule['type'];
            $column = $rule['column'];

            $allowedFilters[] = match ($type) {
                'partial' => AllowedFilter::partial($key, $column),
                'in' => AllowedFilter::callback($key, static function (Builder $builder, mixed $value) use ($column): void {
                    if (! is_scalar($value)) {
                        return;
                    }

                    $values = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
                    if ($values === []) {
                        return;
                    }

                    $builder->whereIn($column, $values);
                }),
                'date_range', 'datetime_range' => AllowedFilter::callback($key, static function (Builder $builder, mixed $value) use ($column, $type): void {
                    if (! is_scalar($value)) {
                        return;
                    }

                    self::applyRange(
                        $builder,
                        $column,
                        trim((string) $value),
                        dateOnly: $type === 'date_range',
                    );
                }),
                default => AllowedFilter::exact($key, $column),
            };
        }

        $qb = QueryBuilder::for($query, $request)
            ->allowedFilters($allowedFilters)
            ->allowedSorts($allowedSorts)
            ->defaultSort($defaultSort);

        if ($allowedIncludes !== []) {
            $qb->allowedIncludes($allowedIncludes);
        }
    }

    /**
     * @return int Per-page value clamped to a safe max.
     */
    public static function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = $request->integer('per_page', $default);

        if ($perPage <= 0) {
            return $default;
        }

        return min($perPage, $max);
    }

    /**
     * Apply a simple multi-column "contains" search using `?q=...`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    public static function applySearch(Builder $query, ?string $q, array $columns): Builder
    {
        $q = trim((string) $q);

        if ($q === '' || $columns === []) {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($q, $columns): void {
            foreach ($columns as $column) {
                $sub->orWhere($column, 'like', "%{$q}%");
            }
        });
    }

    /**
     * Apply a basic equality filter for `?status=...`.
     */
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyStatus(Builder $query, ?string $status, string $column = 'status'): Builder
    {
        $status = trim((string) $status);

        if ($status === '') {
            return $query;
        }

        return $query->where($column, $status);
    }

    /**
     * Apply soft-delete visibility filter using `?trashed=with|only`.
     */
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyTrashed(Builder $query, ?string $value): Builder
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $query;
        }

        $model = $query->getModel();

        if (! method_exists($model, 'restore')) {
            return $query;
        }

        $deletedAtColumn = method_exists($model, 'getQualifiedDeletedAtColumn')
            ? $model->getQualifiedDeletedAtColumn()
            : $model->getTable().'.deleted_at';

        return match ($value) {
            'with' => $query->withoutGlobalScope(SoftDeletingScope::class),
            'only' => $query
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->whereNotNull($deletedAtColumn),
            default => $query,
        };
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function applyRange(Builder $query, string $column, string $value, bool $dateOnly): void
    {
        $range = self::parseRange($value);

        if ($range !== null) {
            [$from, $to] = $range;
            $query->whereBetween($column, [$from, $to]);

            return;
        }

        if ($dateOnly) {
            $query->where($column, $value);

            return;
        }

        $query->whereDate($column, $value);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function parseRange(string $value): ?array
    {
        if (! str_contains($value, '..')) {
            return null;
        }

        [$from, $to] = array_map('trim', explode('..', $value, 2));

        if ($from === '' || $to === '') {
            return null;
        }

        return [$from, $to];
    }
}
