<?php

namespace App\Helpers;

use App\Contracts\SequenceRepository;
use App\Contracts\SettingsRepository;
use App\Models\Plan;
use App\Services\JsonSettingsRepository;
use App\Support\Billing\Currency;
use App\Support\Billing\Discounts;
use App\Support\Billing\TaxRate;
use App\Support\Data;
use App\Support\LocationData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class Helpers
{
    private const DEFAULT_CURRENCY = 'INR';

    private const DEFAULT_EXPENSE_CATEGORIES = [
        'Rent',
        'Utilities',
        'Supplies',
        'Maintenance',
        'Marketing',
        'Equipment',
        'Payroll',
        'Travel',
        'Other',
    ];

    /**
     * @param  array<string, mixed>|null  $override
     */
    public static function setTestSettingsOverride(?array $override): void
    {
        /** @var mixed $repository */
        $repository = app(SettingsRepository::class);

        if ($repository instanceof JsonSettingsRepository) {
            $repository->setTestOverride($override);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSettings(): array
    {
        return app(SettingsRepository::class)->get();
    }

    /**
     * @return array<string, string>
     */
    public static function getCountries(): array
    {
        return app(LocationData::class)->countries();
    }

    /**
     * Get a list of all countries keyed by ISO2 code.
     *
     * @return array<string, string>
     */
    public static function getCountriesWithCodes(): array
    {
        return app(LocationData::class)->countriesWithCodes();
    }

    /**
     * Get the phone code for a specific country name.
     */
    public static function getCountryPhoneCode(?string $countryName): ?string
    {
        return app(LocationData::class)->countryPhoneCode($countryName);
    }

    /**
     * Get the phone placeholder with country code based on settings, falling back to local translation.
     */
    public static function getPhonePlaceholder(): string
    {
        $fallback = Data::string(__('app.placeholders.example_phone'));

        try {
            $settings = self::getSettings();
            $general = is_array($settings['general'] ?? null) ? $settings['general'] : [];
            $countryName = Data::nullableString($general['country'] ?? null);

            $phoneCode = self::getCountryPhoneCode($countryName);

            if (blank($phoneCode)) {
                return $fallback;
            }

            if (preg_match('/^\+\d+/', $fallback)) {
                return preg_replace('/^\+\d+/', '+'.$phoneCode, $fallback) ?? $fallback;
            }

            return '+'.$phoneCode.' '.$fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param  string|null  $countryName  The name of the country
     * @return array<string, string>
     */
    public static function getStates(?string $countryName): array
    {
        return app(LocationData::class)->states($countryName);
    }

    /**
     * @param  string|null  $stateName  The name of the state
     * @return array<string, string>
     */
    public static function getCities(?string $stateName): array
    {
        return app(LocationData::class)->cities($stateName);
    }

    /**
     * @return array<string, string>
     */
    public static function getCurrencies(): array
    {
        return app(LocationData::class)->currencies();
    }

    /**
     * Get the currency code
     */
    public static function getCurrencyCode(): string
    {
        return Currency::codeFromSettings(self::getSettings(), self::DEFAULT_CURRENCY);
    }

    /**
     * Get the number of days before a subscription is considered expiring.
     */
    public static function getSubscriptionExpiringDays(): int
    {
        $settings = self::getSettings();
        $subscriptions = is_array($settings['subscriptions'] ?? null) ? $settings['subscriptions'] : [];
        $days = $subscriptions['expiring_days'] ?? 7;

        if (! is_numeric($days)) {
            return 7;
        }

        return max(1, (int) $days);
    }

    /**
     * Get expense categories from settings (fallback to defaults).
     *
     * @return array<int, string>
     */
    public static function getExpenseCategories(): array
    {
        $settings = self::getSettings();
        $expenses = is_array($settings['expenses'] ?? null) ? $settings['expenses'] : [];
        $categories = $expenses['categories'] ?? null;

        if (! is_array($categories) || empty($categories)) {
            return self::DEFAULT_EXPENSE_CATEGORIES;
        }

        $normalized = [];
        foreach ($categories as $category) {
            $category = trim(Data::string($category));
            if ($category === '') {
                continue;
            }
            $normalized[$category] = $category;
        }

        return array_values($normalized);
    }

    /**
     * Get expense category options for selects.
     *
     * @return array<string, string>
     */
    public static function getExpenseCategoryOptions(): array
    {
        $options = [];
        foreach (self::getExpenseCategories() as $category) {
            $key = Str::slug($category);

            if ($key === '') {
                continue;
            }

            $translationKey = "app.expense_categories.{$key}";
            $options[$key] = Lang::has($translationKey) ? __($translationKey) : $category;
        }

        return $options;
    }

    public static function getExpenseCategoryLabel(?string $key): ?string
    {
        if (blank($key)) {
            return null;
        }

        return self::getExpenseCategoryOptions()[$key] ?? $key;
    }

    /**
     * @return array<string, string>
     */
    public static function getDiscounts(): array
    {
        return Discounts::optionsFromSettings(self::getSettings());
    }

    /**
     * Get the discount amount.
     */
    public static function getDiscountAmount(?float $discount, ?float $fee): float
    {
        return Discounts::amount($discount, $fee);
    }

    /**
     * Get the tax rate from settings.
     */
    public static function getTaxRate(): float
    {
        return TaxRate::fromSettings(self::getSettings());
    }

    /**
     * Format the currency value.
     */
    public static function formatCurrency(?float $value, ?string $currency = null): string
    {
        $currency = $currency ?? self::getCurrencyCode();

        return Currency::format($value, $currency);
    }

    /**
     * Get the currency symbol.
     *
     * @return string The currency symbol.
     */
    public static function getCurrencySymbol(): string
    {
        return Currency::symbol(self::getCurrencyCode());
    }

    /**
     * Generate the next sequential identifier for a given type and model.
     *
     * @param  string  $type  The type identifier used to fetch the corresponding settings.
     * @param  class-string  $modelClass  The fully qualified class name of the Eloquent model to query.
     * @param  string|null  $dateString  A date string used to determine the financial year span.
     * @param  string|null  $modalColumn  The model column to search for the last value (e.g. 'number' or 'code').
     * @return string The newly generated identifier, prefixed and suffixed as configured.
     */
    public static function generateLastNumber(string $type, string $modelClass, ?string $dateString = null, ?string $modalColumn = 'number'): string
    {
        return app(SequenceRepository::class)->generate(
            $type,
            $modelClass,
            $dateString,
            $modalColumn,
        );
    }

    /**
     * Persist the last number for a given type if within the current fiscal year.
     *
     * @param  string  $type  The type of setting to update.
     * @param  string  $newNumber  The new number to set as the last number.
     * @param  string|null  $date  The date to check against the financial year.
     */
    public static function updateLastNumber(string $type, string $newNumber, ?string $date = null): void
    {
        app(SequenceRepository::class)->update($type, $newNumber, $date);
    }

    /**
     * Given a subscription start date and a plan ID, return the Y-m-d end date
     * (or empty string if no valid plan/days).
     */
    public static function calculateSubscriptionEndDate(?string $startDate, ?int $planId): string
    {
        if (! $startDate || ! $planId) {
            return '';
        }

        $plan = Plan::find($planId);
        if (! $plan || ! $plan->days) {
            return '';
        }

        return Carbon::parse($startDate)
            ->addDays((int) $plan->days)
            ->toDateString();
    }
}
