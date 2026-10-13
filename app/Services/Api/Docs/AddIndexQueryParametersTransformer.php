<?php

namespace App\Services\Api\Docs;

use App\Services\Api\ResourceQueryRules;
use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

final class AddIndexQueryParametersTransformer implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (strtoupper($routeInfo->method) !== 'GET') {
            return;
        }

        $uri = (string) $routeInfo->route->uri();

        if (! str_starts_with($uri, 'api/v1/')) {
            return;
        }

        $path = substr($uri, strlen('api/v1/'));

        if ($path === '' || str_contains($path, '/') || str_contains($path, '{')) {
            return;
        }

        $resourceKey = $path;

        try {
            $rules = ResourceQueryRules::forResource($resourceKey);
        } catch (\Throwable) {
            return;
        }

        $qDescription = 'Search term (contains match).';
        if ($rules['searchable'] !== []) {
            $qDescription .= ' Searchable: '.implode(', ', $rules['searchable']).'.';
        }
        $this->add($operation, $this->stringParam('q', $qDescription)->example('alex'));

        $this->add($operation, $this->intParam('page', 'Page number.')->example(1));
        $this->add($operation, $this->intParam('per_page', 'Items per page (max 100).')->example(15));

        $sortDescription = 'Sort keys (comma-separated). Prefix with `-` for desc.';
        if ($rules['sortable'] !== []) {
            $sortDescription .= ' Allowed: '.implode(', ', $rules['sortable']).'. Default: '.$rules['default_sort'].'.';
        }
        $this->add($operation, $this->stringParam('sort', $sortDescription)->example($rules['default_sort']));

        if (in_array($resourceKey, ['members', 'users', 'services', 'plans', 'subscriptions', 'invoices', 'enquiries', 'follow-ups'], true)) {
            $this->add($operation, $this->stringParam('trashed', 'Soft delete visibility: `with` or `only`.')->example('with'));
        }

        if ($rules['includes'] !== []) {
            $this->add($operation, $this->stringParam('include', 'Comma-separated includes. Allowed: '.implode(', ', $rules['includes']).'.')->example($rules['includes'][0]));
        }

        if ($rules['status_column'] !== null) {
            $this->add($operation, $this->stringParam('status', 'Legacy shorthand for `filter[status]`.')->example('active'));
        }

        foreach ($rules['filters'] as $key => $rule) {
            $type = $rule['type'];

            $name = "filter[{$key}]";
            $description = match ($type) {
                'partial' => 'Partial match (contains).',
                'in' => 'Comma-separated list.',
                'date_range', 'datetime_range' => 'Date/datetime filter. Use a single value or range `from..to`.',
                default => 'Exact match.',
            };

            $example = match ($type) {
                'in' => 'a,b,c',
                'date_range', 'datetime_range' => '2026-03-01..2026-03-31',
                default => 'value',
            };

            $this->add($operation, $this->stringParam($name, $description)->example($example));
        }

        if ($resourceKey === 'users') {
            $this->add($operation, $this->boolParam('include_permissions', 'Include permissions array in the response.')->example(false));
        }
    }

    private function add(Operation $operation, Parameter $parameter): void
    {
        foreach ($operation->parameters as $existing) {
            if ($existing instanceof Parameter && $existing->name === $parameter->name && $existing->in === $parameter->in) {
                return;
            }
        }

        $operation->addParameters([$parameter]);
    }

    private function stringParam(string $name, string $description): Parameter
    {
        return Parameter::make($name, 'query')
            ->setSchema(Schema::fromType(new StringType))
            ->description($description);
    }

    private function intParam(string $name, string $description): Parameter
    {
        return Parameter::make($name, 'query')
            ->setSchema(Schema::fromType(new IntegerType))
            ->description($description);
    }

    private function boolParam(string $name, string $description): Parameter
    {
        return Parameter::make($name, 'query')
            ->setSchema(Schema::fromType(new BooleanType))
            ->description($description);
    }
}
