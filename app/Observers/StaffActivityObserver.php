<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffActivity;
use App\Models\StaffAttendance;
use App\Models\Subscription;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class StaffActivityObserver
{
    /** @var array<class-string<Model>, list<string>> */
    private const SAFE_FIELDS = [
        Member::class => ['code', 'name', 'email', 'contact', 'emergency_contact', 'gender', 'dob', 'address', 'country', 'state', 'city', 'pincode', 'source', 'goal', 'status'],
        Enquiry::class => ['user_id', 'name', 'email', 'contact', 'date', 'gender', 'dob', 'status', 'address', 'country', 'state', 'city', 'pincode', 'interested_in', 'source', 'goal', 'start_by', 'lost_reason', 'converted_at'],
        Subscription::class => ['renewed_from_subscription_id', 'member_id', 'plan_id', 'start_date', 'end_date', 'status'],
        Invoice::class => ['number', 'subscription_id', 'date', 'due_date', 'payment_method', 'status', 'tax', 'discount', 'discount_amount', 'paid_amount', 'total_amount', 'due_amount', 'subscription_fee'],
        InvoiceTransaction::class => ['invoice_id', 'type', 'amount', 'occurred_at', 'payment_method', 'reference_id'],
        Expense::class => ['name', 'amount', 'date', 'due_date', 'paid_at', 'category', 'status', 'vendor', 'payment_method', 'payment_reference'],
        Attendance::class => ['member_id', 'subscription_id', 'checked_in_by_id', 'checked_in_at'],
        StaffAttendance::class => ['staff_id', 'checked_in_by_id', 'checked_in_at'],
        Plan::class => ['name', 'code', 'service_id', 'amount', 'days', 'status'],
        Service::class => ['name'],
        User::class => ['name', 'email', 'status', 'contact'],
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();

        $this->record(
            $model,
            'updated',
            array_intersect_key($model->getOriginal(), $changes),
            $changes,
        );
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), []);
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', [], $model->getAttributes());
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, 'force_deleted', $model->getOriginal(), []);
    }

    /**
     * Store only allowlisted values from an authenticated staff action.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function record(Model $model, string $event, array $oldValues, array $newValues): void
    {
        $staff = auth()->user();

        if (! $staff instanceof User) {
            return;
        }

        $fields = self::SAFE_FIELDS[$model::class] ?? [];
        $old = $this->onlySafeValues($oldValues, $fields);
        $new = $this->onlySafeValues($newValues, $fields);

        if ($old === [] && $new === []) {
            return;
        }

        activity(StaffActivity::LOG_NAME)
            ->causedBy($staff)
            ->performedOn($model)
            ->event($event)
            ->withChanges(['old' => $old, 'attributes' => $new])
            ->log($event);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function onlySafeValues(array $values, array $fields): array
    {
        return collect($values)
            ->only($fields)
            ->map(fn (mixed $value): mixed => $this->normalize($value))
            ->all();
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }
}
