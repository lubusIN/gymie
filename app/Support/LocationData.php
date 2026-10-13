<?php

namespace App\Support;

use Nnjeim\World\WorldHelper;

final class LocationData
{
    public function __construct(private readonly WorldHelper $world) {}

    /** @return array<string, string> */
    public function countries(): array
    {
        return collect($this->fallbackCountries())
            ->pluck('name', 'name')
            ->mapWithKeys(fn (mixed $name, mixed $key): array => [Data::string($key) => Data::string($name)])
            ->sortKeys()
            ->all();
    }

    /** @return array<string, string> */
    public function countriesWithCodes(): array
    {
        return collect($this->fallbackCountries())
            ->mapWithKeys(fn (mixed $country): array => [
                Data::string(data_get($country, 'iso2')) => Data::string(data_get($country, 'name')),
            ])
            ->filter(fn (mixed $name, mixed $code): bool => Data::string($code) !== '' && Data::string($name) !== '')
            ->sort()
            ->all();
    }

    public function countryPhoneCode(?string $countryName): ?string
    {
        if (blank($countryName)) {
            return null;
        }

        try {
            $countryResponse = $this->response('countries', [
                'fields' => 'id,name,phone_code',
                'filters' => ['name' => $countryName],
            ]);

            if (! $countryResponse->success || collect($countryResponse->data)->isEmpty()) {
                return null;
            }

            $phoneCode = Data::nullableString(collect($countryResponse->data)->pluck('phone_code')->first());

            if ($phoneCode === null) {
                return null;
            }

            $phoneCode = ltrim($phoneCode, '+');

            return $phoneCode !== '' ? $phoneCode : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, string> */
    public function states(?string $countryName): array
    {
        if (blank($countryName)) {
            return [];
        }

        $countryResponse = $this->response('countries', [
            'filters' => ['name' => $countryName],
        ]);

        if (! $countryResponse->success || collect($countryResponse->data)->isEmpty()) {
            return [];
        }

        $countryId = collect($countryResponse->data)->pluck('id')->first();

        if (! $countryId) {
            return [];
        }

        $stateResponse = $this->response('states', [
            'filters' => ['country_id' => $countryId],
        ]);

        if (! $stateResponse->success) {
            return [];
        }

        return $this->names($stateResponse->data);
    }

    /** @return array<string, string> */
    public function cities(?string $stateName): array
    {
        if (blank($stateName)) {
            return [];
        }

        $stateResponse = $this->response('states', [
            'filters' => ['name' => $stateName],
        ]);

        if (! $stateResponse->success || collect($stateResponse->data)->isEmpty()) {
            return [];
        }

        $stateId = collect($stateResponse->data)->pluck('id')->first();

        if (! $stateId) {
            return [];
        }

        $cityResponse = $this->response('cities', [
            'filters' => ['state_id' => $stateId],
        ]);

        if (! $cityResponse->success || collect($cityResponse->data)->isEmpty()) {
            return [];
        }

        return $this->names($cityResponse->data);
    }

    /** @return array<string, string> */
    public function currencies(): array
    {
        $currencyResponse = $this->response('currencies', [
            'fields' => 'name,code',
        ]);

        if (! $currencyResponse->success) {
            return [];
        }

        return collect($currencyResponse->data)
            ->pluck('name', 'code')
            ->mapWithKeys(fn (mixed $name, mixed $code): array => [Data::string($code) => Data::string($name)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return object{success: bool, data: iterable<array-key, mixed>}
     */
    private function response(string $method, array $parameters = []): object
    {
        try {
            /** @var object{success: bool, data: iterable<array-key, mixed>} $response */
            $response = $this->world->__call($method, [$parameters]);

            return $response;
        } catch (\Throwable) {
            return (object) [
                'success' => false,
                'data' => collect(),
            ];
        }
    }

    /**
     * @param  iterable<array-key, mixed>  $records
     * @return array<string, string>
     */
    private function names(iterable $records): array
    {
        return collect($records)
            ->pluck('name', 'name')
            ->mapWithKeys(fn (mixed $name, mixed $key): array => [Data::string($key) => Data::string($name)])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function fallbackCountries(): array
    {
        $path = base_path('vendor/nnjeim/world/resources/json/countries.json');

        if (! is_file($path)) {
            return [];
        }

        /** @var mixed $countries */
        $countries = json_decode((string) file_get_contents($path), true);

        if (! is_array($countries)) {
            return [];
        }

        return collect($countries)
            ->filter(fn (mixed $country): bool => is_array($country))
            ->map(fn (array $country): array => Data::map($country))
            ->values()
            ->all();
    }
}
