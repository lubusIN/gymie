<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;

trait ResolvesRouteKey
{
    protected function routeKey(string $parameter): int|string
    {
        $value = $this->route($parameter);

        if ($value instanceof Model) {
            $value = $value->getKey();
        }

        if ($value === null) {
            return 0;
        }

        if (is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new \LogicException("Missing or invalid route parameter [{$parameter}].");
    }
}
