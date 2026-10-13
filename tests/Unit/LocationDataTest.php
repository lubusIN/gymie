<?php

use App\Support\LocationData;
use Nnjeim\World\WorldHelper;

it('normalizes collection responses from the world package', function (): void {
    $world = new class extends WorldHelper
    {
        public function __call($function, array $args = []): object
        {
            return match ($function) {
                'countries' => (object) [
                    'success' => true,
                    'data' => collect([['id' => 101, 'name' => 'India']]),
                ],
                'states' => (object) [
                    'success' => true,
                    'data' => collect([['id' => 201, 'name' => 'Maharashtra']]),
                ],
                'cities' => (object) [
                    'success' => true,
                    'data' => collect([['id' => 301, 'name' => 'Mumbai']]),
                ],
                'currencies' => (object) [
                    'success' => true,
                    'data' => collect([['code' => 'INR', 'name' => 'Indian Rupee']]),
                ],
                default => (object) ['success' => false, 'data' => collect()],
            };
        }
    };

    $locations = new LocationData($world);

    expect($locations->states('India'))->toBe(['Maharashtra' => 'Maharashtra'])
        ->and($locations->cities('Maharashtra'))->toBe(['Mumbai' => 'Mumbai'])
        ->and($locations->currencies())->toBe(['INR' => 'Indian Rupee']);
});
