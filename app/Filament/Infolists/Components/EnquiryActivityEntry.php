<?php

namespace App\Filament\Infolists\Components;

use App\Enums\EnquiryActivityType;
use App\Models\Enquiry;
use App\Support\AppConfig;
use Filament\Infolists\Components\Entry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class EnquiryActivityEntry extends Entry
{
    protected string $view = 'filament.infolists.components.enquiry-activity-entry';

    /**
     * Build the presentation-ready activity timeline for the current enquiry.
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

        if (! $record instanceof Enquiry) {
            return [];
        }

        return $record->activitiesAsSubject()
            ->with('causer')
            ->inLog(EnquiryActivityType::LogName)
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
        $event = EnquiryActivityType::tryFrom((string) $activity->event);
        $properties = $activity->properties ?? collect();
        $actor = data_get($activity->causer, 'name') ?: __('app.activity.system');
        $occurredAt = $activity->created_at?->timezone(AppConfig::timezone());

        return [
            'description' => $this->description($event, (string) $actor, $properties->all()),
            'body' => $this->body($event, $properties->all()),
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
    private function description(?EnquiryActivityType $event, string $actor, array $properties): string
    {
        return match ($event) {
            EnquiryActivityType::LeadCreated => __('app.activity.events.lead_created', compact('actor')),
            EnquiryActivityType::LeadOwnerChanged => __('app.activity.events.lead_owner_changed', [
                'actor' => $actor,
                'old' => $this->valueOrFallback($properties['old'] ?? null, __('app.activity.unassigned')),
                'new' => $this->valueOrFallback($properties['new'] ?? null, __('app.activity.unassigned')),
            ]),
            EnquiryActivityType::LeadSourceChanged => __('app.activity.events.lead_source_changed', [
                'actor' => $actor,
                'old' => $this->optionLabel('source', $properties['old'] ?? null),
                'new' => $this->optionLabel('source', $properties['new'] ?? null),
            ]),
            EnquiryActivityType::FollowUpScheduled => __('app.activity.events.follow_up_scheduled', [
                'actor' => $actor,
                'method' => $this->optionLabel('follow_up_method', $properties['method'] ?? null),
                'date' => $this->dateLabel($properties['schedule_date'] ?? null),
            ]),
            EnquiryActivityType::FollowUpCompleted => __('app.activity.events.follow_up_completed', compact('actor')),
            EnquiryActivityType::NoteAdded => __('app.activity.events.note_added', compact('actor')),
            EnquiryActivityType::ConvertedToMember => __('app.activity.events.converted_to_member', [
                'actor' => $actor,
                'member' => $this->valueOrFallback($properties['member'] ?? null),
            ]),
            EnquiryActivityType::MarkedLost => __('app.activity.events.marked_lost', compact('actor')),
            default => __('app.activity.events.updated', [
                'actor' => $actor,
                'event' => Str::headline((string) $event?->value),
            ]),
        };
    }

    /**
     * Get the optional detail displayed below an activity sentence.
     *
     * @param  array<string, mixed>  $properties
     */
    private function body(?EnquiryActivityType $event, array $properties): ?string
    {
        $value = match ($event) {
            EnquiryActivityType::NoteAdded => $properties['note'] ?? null,
            EnquiryActivityType::FollowUpCompleted => $properties['outcome'] ?? null,
            EnquiryActivityType::MarkedLost => $properties['reason'] ?? null,
            default => null,
        };

        return filled($value) ? (string) $value : null;
    }

    /**
     * Get the icon that identifies an activity event.
     */
    private function icon(?EnquiryActivityType $event): string
    {
        return match ($event) {
            EnquiryActivityType::LeadCreated => 'heroicon-o-user-plus',
            EnquiryActivityType::LeadOwnerChanged => 'heroicon-o-user-circle',
            EnquiryActivityType::LeadSourceChanged => 'heroicon-o-megaphone',
            EnquiryActivityType::FollowUpScheduled => 'heroicon-o-calendar-days',
            EnquiryActivityType::FollowUpCompleted => 'heroicon-o-check-circle',
            EnquiryActivityType::NoteAdded => 'heroicon-o-chat-bubble-left-ellipsis',
            EnquiryActivityType::ConvertedToMember => 'heroicon-o-arrows-right-left',
            EnquiryActivityType::MarkedLost => 'heroicon-o-x-circle',
            default => 'heroicon-o-clock',
        };
    }

    /**
     * Get the semantic color for an activity event.
     */
    private function color(?EnquiryActivityType $event): string
    {
        return match ($event) {
            EnquiryActivityType::FollowUpScheduled => 'warning',
            EnquiryActivityType::FollowUpCompleted,
            EnquiryActivityType::ConvertedToMember => 'success',
            EnquiryActivityType::MarkedLost => 'danger',
            EnquiryActivityType::LeadCreated,
            EnquiryActivityType::LeadOwnerChanged,
            EnquiryActivityType::LeadSourceChanged => 'primary',
            default => 'gray',
        };
    }

    /**
     * Translate a stored option value when a label exists.
     */
    private function optionLabel(string $group, mixed $value): string
    {
        if (blank($value)) {
            return __('app.placeholders.na');
        }

        $key = "app.options.{$group}.{$value}";

        return Lang::has($key) ? __($key) : Str::headline((string) $value);
    }

    /**
     * Format the scheduled date for the activity sentence.
     */
    private function dateLabel(mixed $value): string
    {
        if (blank($value)) {
            return __('app.placeholders.na');
        }

        return Carbon::parse((string) $value)
            ->timezone(AppConfig::timezone())
            ->format('d M Y');
    }

    /**
     * Convert an optional property into a readable fallback value.
     */
    private function valueOrFallback(mixed $value, ?string $fallback = null): string
    {
        return filled($value)
            ? (string) $value
            : ($fallback ?? __('app.placeholders.na'));
    }
}
