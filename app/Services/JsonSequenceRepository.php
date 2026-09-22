<?php

namespace App\Services;

use App\Contracts\SequenceRepository;
use App\Contracts\SettingsRepository;
use App\Helpers\Helpers;
use App\Support\Data;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * JSON-backed sequence generator (OSS default).
 *
 * Reads prefix / last_number from SettingsRepository and also inspects the DB
 * to avoid collisions within the current fiscal span.
 */
class JsonSequenceRepository implements SequenceRepository
{
    public function __construct(
        protected SettingsRepository $settingsRepository,
    ) {}

    /**
     * Generate the next number for a given entity type.
     *
     * @param  class-string  $modelClass
     */
    public function generate(
        string $type,
        string $modelClass,
        ?string $dateString = null,
        ?string $modelColumn = 'number',
    ): string {
        $date = Helpers::parseDate($dateString);
        [$start, $end] = Helpers::getFiscalSpan($date);
        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        $dateColumn = Schema::hasColumn($table, 'date')
            ? 'date'
            : 'created_at';

        return Cache::lock($this->lockName($type, $start->toDateString()), 10)
            ->block(5, function () use ($type, $modelClass, $modelColumn, $dateColumn, $start, $end): string {
                $settings = $this->freshSettings();
                $rawPrefix = data_get($settings, "{$type}.prefix", '');
                $rawSaved = data_get($settings, "{$type}.last_number", '');

                $prefix = trim(Data::string($rawPrefix), '-');
                $prefix = filled($prefix) ? $prefix : 'GY';
                $separator = $prefix !== '' ? '-' : '';
                $match = $prefix.$separator;

                $lastFromDb = $modelClass::query()
                    ->whereBetween($dateColumn, [$start->toDateString(), $end->toDateString()])
                    ->pluck($modelColumn ?? 'number')
                    ->map(
                        fn ($raw) => Str::of(Data::string($raw))
                            ->whenStartsWith($match, fn ($value) => $value->after($match))
                            ->__toString()
                    )
                    ->map(fn ($value): int => is_numeric($value) ? (int) $value : 0)
                    ->max() ?: 0;

                $lastFromSettings = Str::of(Data::string($rawSaved))
                    ->whenStartsWith($match, fn ($value) => $value->after($match))
                    ->__toString();
                $lastFromSettings = is_numeric($lastFromSettings)
                    ? (int) $lastFromSettings
                    : 0;

                $next = max($lastFromDb, $lastFromSettings) + 1;
                $number = str($prefix)
                    ->when($separator !== '', fn ($value) => $value->append($separator))
                    ->append((string) $next)
                    ->__toString();

                $this->storeLastNumber($settings, $type, $prefix, $next);

                return $number;
            });
    }

    public function update(
        string $type,
        string $newNumber,
        ?string $date = null,
    ): void {
        $date = Helpers::parseDate($date);
        [$start, $end] = Helpers::getFiscalSpan($date);

        if (! $date->between($start, $end)) {
            return;
        }

        Cache::lock($this->lockName($type, $start->toDateString()), 10)
            ->block(5, function () use ($type, $newNumber): void {
                $settings = $this->freshSettings();
                $rawPrefix = data_get($settings, "{$type}.prefix", 'GY');
                $prefix = trim(Data::string($rawPrefix), '-');

                $numericPart = Str::of($newNumber)
                    ->match('/(\\d+)$/')
                    ->__toString();

                if ($numericPart === '' || ! ctype_digit($numericPart)) {
                    return;
                }

                $incoming = (int) $numericPart;
                $rawStored = data_get($settings, "{$type}.last_number", '');
                $storedNumeric = Str::of(Data::string($rawStored))
                    ->match('/(\\d+)$/')
                    ->__toString();
                $current = ctype_digit($storedNumeric) ? (int) $storedNumeric : 0;

                if ($incoming <= $current) {
                    return;
                }

                $this->storeLastNumber($settings, $type, $prefix, $incoming);
            });
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function storeLastNumber(array $settings, string $type, string $prefix, int $number): void
    {
        if (! isset($settings[$type]) || ! is_array($settings[$type])) {
            $settings[$type] = [];
        }

        /** @var array<string, mixed> $typeSettings */
        $typeSettings = $settings[$type];
        $typeSettings['last_number'] = $number;
        $typeSettings['prefix'] = $prefix;
        $settings[$type] = $typeSettings;

        $this->settingsRepository->put($settings);
    }

    private function lockName(string $type, string $periodStart): string
    {
        return "gymie-sequence:{$type}:{$periodStart}";
    }

    /**
     * Reload JSON settings after acquiring the lock so a waiting request does
     * not reserve a number from stale request-cached state.
     *
     * @return array<string, mixed>
     */
    private function freshSettings(): array
    {
        if ($this->settingsRepository instanceof JsonSettingsRepository) {
            return $this->settingsRepository->fresh();
        }

        return $this->settingsRepository->get();
    }
}
