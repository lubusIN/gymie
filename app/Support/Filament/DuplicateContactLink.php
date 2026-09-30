<?php

namespace App\Support\Filament;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Enquiry;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Schemas\Components\Callout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class DuplicateContactLink
{
    private function __construct() {}

    /**
     * Build an authorized link to a record that shares an email or contact number.
     *
     * @return array<Callout>
     */
    public static function make(
        string $field,
        ?string $value,
        ?Model $record = null,
        ?int $sourceEnquiryId = null,
    ): array {
        $value = trim((string) $value);

        if ($value === '' || ! in_array($field, ['email', 'contact'], true)) {
            return [];
        }

        $ignoredMemberId = $record instanceof Member && $record->exists
            ? (int) $record->getKey()
            : null;
        $ignoredEnquiryId = match (true) {
            $sourceEnquiryId !== null => $sourceEnquiryId,
            $record instanceof Enquiry && $record->exists => (int) $record->getKey(),
            $record instanceof Member && $record->enquiry_id !== null => (int) $record->enquiry_id,
            default => null,
        };

        $matches = self::memberMatches($field, $value, $ignoredMemberId, $ignoredEnquiryId)
            ->concat(self::enquiryMatches($field, $value, $ignoredEnquiryId))
            ->filter(fn (array $match): bool => auth()->user()?->can('view', $match['record']) ?? false)
            ->values();

        $match = $matches->first();

        if ($match === null) {
            return [];
        }

        return [
            Callout::make()
                ->description(__('app.duplicates.description', [
                    'field' => mb_strtolower((string) __("app.fields.{$field}")),
                ]))
                ->warning()
                ->actions([
                    Action::make("check_duplicate_{$field}_{$match['type']}_{$match['record']->getKey()}")
                        ->label(__('app.duplicates.check_record'))
                        ->url($match['url'])
                        ->openUrlInNewTab(),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Find matching members while excluding the record being edited or converted.
     *
     * @return Collection<int, array{type: string, record: Member, url: string}>
     */
    private static function memberMatches(
        string $field,
        string $value,
        ?int $ignoredMemberId,
        ?int $ignoredEnquiryId,
    ): Collection {
        return Member::query()
            ->select(['id', 'enquiry_id'])
            ->where($field, $value)
            ->when($ignoredMemberId !== null, fn ($query) => $query->whereKeyNot($ignoredMemberId))
            ->when($ignoredEnquiryId !== null, fn ($query) => $query->where(function ($query) use ($ignoredEnquiryId): void {
                $query
                    ->whereNull('enquiry_id')
                    ->orWhere('enquiry_id', '!=', $ignoredEnquiryId);
            }))
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Member $member): array => [
                'type' => 'member',
                'record' => $member,
                'url' => MemberResource::getUrl('view', ['record' => $member]),
            ]);
    }

    /**
     * Find matching enquiries while excluding the current conversion source.
     *
     * @return Collection<int, array{type: string, record: Enquiry, url: string}>
     */
    private static function enquiryMatches(
        string $field,
        string $value,
        ?int $ignoredEnquiryId,
    ): Collection {
        return Enquiry::query()
            ->select('id')
            ->where($field, $value)
            ->when($ignoredEnquiryId !== null, fn ($query) => $query->whereKeyNot($ignoredEnquiryId))
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Enquiry $enquiry): array => [
                'type' => 'enquiry',
                'record' => $enquiry,
                'url' => EnquiryResource::getUrl('view', ['record' => $enquiry]),
            ]);
    }
}
