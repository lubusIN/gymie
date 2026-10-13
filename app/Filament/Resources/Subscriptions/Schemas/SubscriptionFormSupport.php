<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Helpers\Helpers;
use App\Models\Plan;
use App\Support\Billing\InvoiceCalculator;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class SubscriptionFormSupport
{
    private function __construct() {}

    public static function planOptionLabel(Plan $plan): string
    {
        $days = Data::int($plan->days);

        return sprintf(
            '%s – %s (%s%s | %d %s)',
            $plan->code,
            $plan->name,
            Helpers::getCurrencySymbol(),
            round((float) $plan->amount),
            $days,
            $days === 1 ? __('app.units.day') : __('app.units.days'),
        );
    }

    public static function recalculateInvoiceSummary(
        Get $get,
        Set $set,
        ?float $fee = null,
        ?float $taxRate = null,
    ): void {
        $fee ??= self::floatState($get, 'subscription_fee');
        $taxRate ??= (float) (Helpers::getTaxRate() ?: 0);

        $discountAmount = self::floatState($get, 'discount_amount');
        $paid = self::floatState($get, 'paid_amount');

        if (PaymentMethod::isOnline(self::stringState($get, 'payment_method'))) {
            $paid = 0;
        }

        $summary = InvoiceCalculator::summary(
            $fee,
            $taxRate,
            $discountAmount,
            $paid,
        );

        $set('subscription_fee', $summary['fee']);
        $set('tax', $summary['tax']);
        $set('discount_amount', $summary['discount_amount']);
        $set('total_amount', $summary['total']);
        $set('paid_amount', $summary['paid']);
        $set('due_amount', $summary['due']);
    }

    public static function stringState(Get $get, string $path): ?string
    {
        return Data::nullableString($get($path));
    }

    public static function intState(Get $get, string $path): ?int
    {
        $value = $get($path);

        return is_numeric($value) ? (int) $value : null;
    }

    public static function floatState(Get $get, string $path): float
    {
        return Data::float($get($path));
    }

    public static function subscriptionEndDate(Get $get): string
    {
        return Helpers::calculateSubscriptionEndDate(
            self::stringState($get, 'start_date'),
            self::intState($get, 'plan_id'),
        );
    }

    public static function updateEndDate(Get $get, Set $set): void
    {
        $set('end_date', self::subscriptionEndDate($get));
    }

    public static function planFee(Get $get): float
    {
        $planId = self::intState($get, 'plan_id');
        $plan = $planId !== null ? Plan::find($planId) : null;

        return round(Data::float($plan?->amount));
    }
}
