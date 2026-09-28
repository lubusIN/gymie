<?php

namespace App\Filament\Infolists\Components;

use App\Enums\EnquiryActivityType;
use App\Enums\MemberActivityType;
use App\Enums\Status;
use App\Enums\SubscriptionActivityType;
use App\Helpers\Helpers;
use App\Models\Enquiry;
use App\Models\Member;
use App\Models\Subscription;
use App\Support\AppConfig;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityTimelineEntry extends Entry
{
    protected string $view = 'filament.infolists.components.activity-timeline-entry';

    /**
     * Build the presentation-ready activity timeline for the current record.
     *
     * @return list<array{
     *     description: string,
     *     body: string|null,
     *     icon: string,
     *     color: string,
     *     relative_time: string,
     *     timestamp: string
     * }>
     */
    public function getTimeline(): array
    {
        $record = $this->getRecord();
        $logName = $this->logName($record);

        if ($logName === null) {
            return [];
        }

        return $record->activitiesAsSubject()
            ->with('causer')
            ->inLog($logName)
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Activity $activity): array => $this->present($activity))
            ->all();
    }

    /**
     * Transform one stored activity into a timeline item.
     *
     * @return array{
     *     description: string,
     *     body: string|null,
     *     icon: string,
     *     color: string,
     *     relative_time: string,
     *     timestamp: string
     * }
     */
    private function present(Activity $activity): array
    {
        $event = (string) $activity->event;
        $properties = $activity->properties->all();
        $actor = data_get($activity->causer, 'name') ?: __('app.activity.system');
        $occurredAt = $activity->created_at?->timezone(AppConfig::timezone());

        return [
            'description' => $this->description($event, (string) $actor, $properties),
            'body' => $this->body($event, $properties),
            'icon' => $this->icon($event),
            'color' => $this->color($event),
            'relative_time' => $occurredAt?->diffForHumans() ?? __('app.placeholders.na'),
            'timestamp' => $occurredAt?->format('d M Y, h:i A') ?? __('app.placeholders.na'),
        ];
    }

    /**
     * Resolve a readable sentence for an activity event.
     *
     * @param  array<string, mixed>  $properties
     */
    private function description(string $event, string $actor, array $properties): string
    {
        return match ($event) {
            EnquiryActivityType::LeadCreated->value => __('app.activity.events.lead_created', compact('actor')),
            EnquiryActivityType::LeadOwnerChanged->value => __('app.activity.events.lead_owner_changed', [
                'actor' => $actor,
                'old' => $this->valueOrFallback($properties['old'] ?? null, __('app.activity.unassigned')),
                'new' => $this->valueOrFallback($properties['new'] ?? null, __('app.activity.unassigned')),
            ]),
            EnquiryActivityType::LeadSourceChanged->value => __('app.activity.events.lead_source_changed', [
                'actor' => $actor,
                'old' => $this->optionLabel('source', $properties['old'] ?? null),
                'new' => $this->optionLabel('source', $properties['new'] ?? null),
            ]),
            EnquiryActivityType::FollowUpScheduled->value => __('app.activity.events.follow_up_scheduled', [
                'actor' => $actor,
                'method' => $this->optionLabel('follow_up_method', $properties['method'] ?? null),
                'date' => $this->dateLabel($properties['schedule_date'] ?? null),
            ]),
            EnquiryActivityType::FollowUpCompleted->value => __('app.activity.events.follow_up_completed', compact('actor')),
            EnquiryActivityType::NoteAdded->value => __('app.activity.events.note_added', compact('actor')),
            EnquiryActivityType::ConvertedToMember->value => __('app.activity.events.converted_to_member', [
                'actor' => $actor,
                'member' => $this->valueOrFallback($properties['member'] ?? null),
            ]),
            EnquiryActivityType::MarkedLost->value => __('app.activity.events.marked_lost', compact('actor')),
            MemberActivityType::MemberCreated->value => __('app.activity.events.member_created', compact('actor')),
            MemberActivityType::MemberUpdated->value => __('app.activity.events.member_updated', [
                'actor' => $actor,
                'fields' => $this->fieldList($properties['fields'] ?? []),
            ]),
            MemberActivityType::MemberStatusChanged->value => __('app.activity.events.member_status_changed', [
                'actor' => $actor,
                'old' => $this->statusLabel($properties['old'] ?? null),
                'new' => $this->statusLabel($properties['new'] ?? null),
            ]),
            MemberActivityType::SubscriptionCreated->value => __('app.activity.events.subscription_created', [
                'actor' => $actor,
                'plan' => $this->valueOrFallback($properties['plan'] ?? null),
            ]),
            MemberActivityType::SubscriptionRenewed->value => __('app.activity.events.subscription_renewed', [
                'actor' => $actor,
                'plan' => $this->valueOrFallback($properties['plan'] ?? null),
            ]),
            MemberActivityType::SubscriptionStatusChanged->value => __('app.activity.events.subscription_status_changed', [
                'actor' => $actor,
                'plan' => $this->valueOrFallback($properties['plan'] ?? null),
                'old' => $this->statusLabel($properties['old'] ?? null),
                'new' => $this->statusLabel($properties['new'] ?? null),
            ]),
            SubscriptionActivityType::InvoiceCreated->value => __('app.activity.events.invoice_created', [
                'actor' => $actor,
                'invoice' => $this->valueOrFallback($properties['invoice'] ?? null),
                'amount' => $this->currencyLabel($properties['amount'] ?? null),
            ]),
            SubscriptionActivityType::InvoiceStatusChanged->value => $this->invoiceStatusChangedDescription($actor, $properties),
            SubscriptionActivityType::PaymentRecorded->value => __('app.activity.events.payment_recorded', [
                'actor' => $actor,
                'amount' => $this->currencyLabel($properties['amount'] ?? null),
                'invoice' => $this->valueOrFallback($properties['invoice'] ?? null),
            ]),
            SubscriptionActivityType::RefundRecorded->value => __('app.activity.events.refund_recorded', [
                'actor' => $actor,
                'amount' => $this->currencyLabel($properties['amount'] ?? null),
                'invoice' => $this->valueOrFallback($properties['invoice'] ?? null),
            ]),
            default => __('app.activity.events.updated', [
                'actor' => $actor,
                'event' => Str::headline($event),
            ]),
        };
    }

    /**
     * Get the optional detail displayed below an activity sentence.
     *
     * @param  array<string, mixed>  $properties
     */
    private function body(string $event, array $properties): ?string
    {
        $value = match ($event) {
            EnquiryActivityType::NoteAdded->value => $properties['note'] ?? null,
            EnquiryActivityType::FollowUpCompleted->value => $properties['outcome'] ?? null,
            EnquiryActivityType::MarkedLost->value => $properties['reason'] ?? null,
            SubscriptionActivityType::PaymentRecorded->value,
            SubscriptionActivityType::RefundRecorded->value => $properties['note'] ?? null,
            default => null,
        };

        return filled($value) ? (string) $value : null;
    }

    /**
     * Describe an invoice status change without presenting a missing prior state as N/A.
     *
     * @param  array<string, mixed>  $properties
     */
    private function invoiceStatusChangedDescription(string $actor, array $properties): string
    {
        $replacements = [
            'actor' => $actor,
            'invoice' => $this->valueOrFallback($properties['invoice'] ?? null),
            'new' => $this->statusLabel($properties['new'] ?? null),
        ];

        if (blank($properties['old'] ?? null)) {
            return __('app.activity.events.invoice_status_set', $replacements);
        }

        return __('app.activity.events.invoice_status_changed', [
            ...$replacements,
            'old' => $this->statusLabel($properties['old']),
        ]);
    }

    private function icon(string $event): string
    {
        return match ($event) {
            EnquiryActivityType::LeadCreated->value,
            MemberActivityType::MemberCreated->value => 'heroicon-o-user-plus',
            EnquiryActivityType::LeadOwnerChanged->value => 'heroicon-o-user-circle',
            EnquiryActivityType::LeadSourceChanged->value => 'heroicon-o-megaphone',
            EnquiryActivityType::FollowUpScheduled->value => 'heroicon-o-calendar-days',
            EnquiryActivityType::FollowUpCompleted->value => 'heroicon-o-check-circle',
            EnquiryActivityType::NoteAdded->value => 'heroicon-o-chat-bubble-left-ellipsis',
            EnquiryActivityType::ConvertedToMember->value => 'heroicon-o-arrows-right-left',
            EnquiryActivityType::MarkedLost->value => 'heroicon-o-x-circle',
            MemberActivityType::MemberUpdated->value => 'heroicon-o-pencil-square',
            MemberActivityType::MemberStatusChanged->value => 'heroicon-o-arrow-path',
            MemberActivityType::SubscriptionCreated->value => 'heroicon-o-ticket',
            MemberActivityType::SubscriptionRenewed->value => 'heroicon-o-arrow-path-rounded-square',
            MemberActivityType::SubscriptionStatusChanged->value => 'heroicon-o-adjustments-horizontal',
            SubscriptionActivityType::InvoiceCreated->value => 'heroicon-o-document-plus',
            SubscriptionActivityType::InvoiceStatusChanged->value => 'heroicon-o-document-check',
            SubscriptionActivityType::PaymentRecorded->value => 'heroicon-o-banknotes',
            SubscriptionActivityType::RefundRecorded->value => 'heroicon-o-arrow-uturn-left',
            default => 'heroicon-o-clock',
        };
    }

    private function color(string $event): string
    {
        return match ($event) {
            EnquiryActivityType::FollowUpScheduled->value => 'warning',
            EnquiryActivityType::FollowUpCompleted->value,
            EnquiryActivityType::ConvertedToMember->value,
            MemberActivityType::SubscriptionCreated->value,
            MemberActivityType::SubscriptionRenewed->value,
            SubscriptionActivityType::InvoiceCreated->value,
            SubscriptionActivityType::PaymentRecorded->value => 'success',
            EnquiryActivityType::MarkedLost->value => 'danger',
            SubscriptionActivityType::RefundRecorded->value => 'warning',
            EnquiryActivityType::LeadCreated->value,
            EnquiryActivityType::LeadOwnerChanged->value,
            EnquiryActivityType::LeadSourceChanged->value,
            MemberActivityType::MemberCreated->value,
            MemberActivityType::MemberStatusChanged->value,
            MemberActivityType::SubscriptionStatusChanged->value,
            SubscriptionActivityType::InvoiceStatusChanged->value => 'primary',
            default => 'gray',
        };
    }

    private function logName(?Model $record): ?string
    {
        return match (true) {
            $record instanceof Enquiry => EnquiryActivityType::LogName,
            $record instanceof Member => MemberActivityType::LogName,
            $record instanceof Subscription => SubscriptionActivityType::LogName,
            default => null,
        };
    }

    private function currencyLabel(mixed $value): string
    {
        return is_numeric($value)
            ? Helpers::formatCurrency((float) $value)
            : __('app.placeholders.na');
    }

    private function fieldList(mixed $fields): string
    {
        if (! is_array($fields) || $fields === []) {
            return __('app.placeholders.na');
        }

        return collect($fields)
            ->map(function (mixed $field): string {
                $key = 'app.fields.'.(string) $field;

                return Lang::has($key) ? __($key) : Str::headline((string) $field);
            })
            ->join(', ');
    }

    private function statusLabel(mixed $value): string
    {
        if (blank($value)) {
            return __('app.placeholders.na');
        }

        return Status::tryFrom((string) $value)?->getLabel() ?? Str::headline((string) $value);
    }

    private function optionLabel(string $group, mixed $value): string
    {
        if (blank($value)) {
            return __('app.placeholders.na');
        }

        $key = "app.options.{$group}.{$value}";

        return Lang::has($key) ? __($key) : Str::headline((string) $value);
    }

    private function dateLabel(mixed $value): string
    {
        if (blank($value)) {
            return __('app.placeholders.na');
        }

        return Carbon::parse((string) $value)
            ->timezone(AppConfig::timezone())
            ->format('d M Y');
    }

    private function valueOrFallback(mixed $value, ?string $fallback = null): string
    {
        return filled($value)
            ? (string) $value
            : ($fallback ?? __('app.placeholders.na'));
    }
}
