<?php

namespace App\Models;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\Users\UserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Read model for staff-caused records in the shared activity log.
 *
 * @property-read Model|null $subject
 * @property-read User|null $causer
 */
class StaffActivity extends Activity
{
    public const LOG_NAME = 'staff';

    /** @return Builder<static> */
    public function scopeStaff(Builder $query): Builder
    {
        return $query
            ->where('log_name', self::LOG_NAME)
            ->where('causer_type', User::class)
            ->whereNotNull('causer_id');
    }

    public function moduleLabel(): string
    {
        return Str::headline(class_basename((string) $this->subject_type));
    }

    public function subjectLabel(): string
    {
        $subject = $this->subject;

        if ($subject) {
            foreach (['name', 'number', 'code'] as $attribute) {
                if (filled($value = $subject->getAttribute($attribute))) {
                    return (string) $value;
                }
            }
        }

        return $this->moduleLabel().' #'.$this->subject_id;
    }

    public function subjectUrl(): ?string
    {
        if (! $this->subject) {
            return null;
        }

        $resource = match ($this->subject_type) {
            Member::class => MemberResource::class,
            Enquiry::class => EnquiryResource::class,
            Subscription::class => SubscriptionResource::class,
            Invoice::class => InvoiceResource::class,
            User::class => UserResource::class,
            default => null,
        };

        if ($resource === null) {
            return null;
        }

        if (! $resource::canView($this->subject)) {
            return null;
        }

        return $resource::getUrl('view', ['record' => $this->subject]);
    }

    public function actionLabel(): string
    {
        return __("app.staff_activity.events.{$this->event}");
    }

    public function changedFieldsLabel(): string
    {
        return $this->newValues()
            ->merge($this->oldValues())
            ->keys()
            ->unique()
            ->map(fn (string $field): string => Str::headline($field))
            ->implode(', ');
    }

    /** @return Collection<string, mixed> */
    public function oldValues(): Collection
    {
        return collect($this->attribute_changes?->get('old', []));
    }

    /** @return Collection<string, mixed> */
    public function newValues(): Collection
    {
        return collect($this->attribute_changes?->get('attributes', []));
    }
}
