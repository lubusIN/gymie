<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\Helpers;
use App\Http\Requests\Api\V1\SubscriptionRenewRequest;
use App\Http\Requests\Api\V1\SubscriptionStoreRequest;
use App\Http\Requests\Api\V1\SubscriptionUpdateRequest;
use App\Http\Resources\V1\InvoiceResource;
use App\Http\Resources\V1\SubscriptionResource;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Api\QueryFilters;
use App\Services\Subscriptions\SubscriptionRenewalService;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Subscriptions CRUD endpoints.
 */
class SubscriptionsController extends ApiController
{
    private const RESOURCE_KEY = 'subscriptions';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Subscription');

        $query = Subscription::query()->with(['member', 'plan']);

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return SubscriptionResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubscriptionStoreRequest $request): SubscriptionResource
    {
        $this->requirePermission($request, 'Create:Subscription');

        $data = $request->validated();

        $timezone = AppConfig::timezone();
        $today = Carbon::today($timezone);

        $plan = Plan::findOrFail(Data::int($data['plan_id'] ?? null));

        $startDate = Carbon::parse(Data::string($data['start_date'] ?? null))->toDateString();
        $endDate = Data::string($data['end_date'] ?? null) ?: Helpers::calculateSubscriptionEndDate($startDate, Data::int($plan->id));
        $endDate = Carbon::parse($endDate)->toDateString();

        $status = $data['status'] ?? match (true) {
            Carbon::parse($startDate)->gt($today) => 'upcoming',
            Carbon::parse($endDate)->lt($today) => 'expired',
            default => 'ongoing',
        };

        $invoiceData = $data['invoice'] ?? null;
        unset($data['invoice'], $data['status'], $data['end_date'], $data['start_date']);

        $subscription = Subscription::create([
            ...$data,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);

        if (is_array($invoiceData)) {
            $fee = round((float) $plan->amount, 2);

            $discountPct = max(Data::int($invoiceData['discount'] ?? 0), 0);
            $discountAmount = Data::float($invoiceData['discount_amount'] ?? 0);
            $discountAmount = min(max($discountAmount, 0), $fee);
            if ($discountPct > 0 && $discountAmount <= 0) {
                $discountAmount = (float) Helpers::getDiscountAmount($discountPct, $fee);
            }

            $paymentMethod = Data::nullableString($invoiceData['payment_method'] ?? null);
            $paidAmount = max(Data::float($invoiceData['paid_amount'] ?? 0), 0);
            if (PaymentMethod::isOnline($paymentMethod)) {
                $paidAmount = 0;
            }

            $invoiceDate = Carbon::parse(Data::string($invoiceData['date'] ?? $startDate))->toDateString();
            $invoiceDueDate = Carbon::parse(Data::string($invoiceData['due_date'] ?? $invoiceDate))->toDateString();

            $subscription->invoices()->create([
                'number' => $invoiceData['number'] ?? null,
                'date' => $invoiceDate,
                'due_date' => $invoiceDueDate,
                'payment_method' => $paymentMethod,
                'discount' => $discountPct ?: null,
                'discount_amount' => $discountAmount ?: null,
                'discount_note' => $invoiceData['discount_note'] ?? null,
                'paid_amount' => $paidAmount,
                'subscription_fee' => $fee,
                'status' => 'issued',
            ]);
        }

        $subscription->load(['member', 'plan', 'invoices']);

        return new SubscriptionResource($subscription);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->requirePermission($request, 'View:Subscription');

        $subscription->load(['member', 'plan']);

        return new SubscriptionResource($subscription);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubscriptionUpdateRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->requirePermission($request, 'Update:Subscription');

        $subscription->update($request->validated());
        $subscription->load(['member', 'plan']);

        return new SubscriptionResource($subscription);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Subscription $subscription): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:Subscription', $subscription);
    }

    /**
     * Restore a soft deleted subscription.
     */
    public function restore(Request $request, int $subscription): SubscriptionResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:Subscription', Subscription::class, $subscription);
        $record->load(['member', 'plan']);

        return new SubscriptionResource($record->refresh());
    }

    /**
     * Permanently delete a subscription.
     */
    public function forceDelete(Request $request, int $subscription): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:Subscription', Subscription::class, $subscription);

        return $this->noContent();
    }

    /**
     * Renew a subscription and create a new invoice.
     */
    public function renew(
        SubscriptionRenewRequest $request,
        Subscription $subscription,
        SubscriptionRenewalService $subscriptionRenewalService,
    ): JsonResponse {
        $this->requirePermission($request, 'Update:Subscription');

        /** @var array{
         *   plan_id: int,
         *   start_date: string,
         *   end_date?: string|null,
         *   invoice?: array{
         *     number?: string|null,
         *     date?: string|null,
         *     due_date?: string|null,
         *     payment_method?: string|null,
         *     discount?: float|int|string|null,
         *     discount_amount?: float|int|string|null,
         *     discount_note?: string|null,
         *     paid_amount?: float|int|string|null
         *   }
         * } $validated
         */
        $validated = $request->validated();

        $result = $subscriptionRenewalService->renew($subscription, $validated);

        $newSubscription = $result['subscription'];
        $invoice = $result['invoice'];

        $newSubscription->load(['member', 'plan']);
        $invoice->load(['subscription.member', 'subscription.plan']);

        return response()->json([
            'data' => [
                'subscription' => new SubscriptionResource($newSubscription),
                'invoice' => new InvoiceResource($invoice),
            ],
        ]);
    }
}
