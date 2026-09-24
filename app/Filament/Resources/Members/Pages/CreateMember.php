<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\Status;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Enquiry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Gate;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected static bool $canCreateAnother = false;

    public ?int $enquiryId = null;

    public function mount(): void
    {
        parent::mount();

        $enquiryId = request()->integer('enquiry_id');

        if ($enquiryId === 0) {
            return;
        }

        $enquiry = Enquiry::query()
            ->whereKey($enquiryId)
            ->whereIn('status', [
                Status::Lead->value,
                Status::FollowUpScheduled->value,
            ])
            ->whereDoesntHave('member')
            ->first();

        if (! $enquiry) {
            return;
        }

        Gate::authorize('view', $enquiry);

        $this->enquiryId = $enquiry->id;
        $this->form->fill([
            ...($this->data ?? []),
            'name' => $enquiry->name,
            'email' => $enquiry->email,
            'contact' => $enquiry->contact,
            'gender' => $enquiry->gender,
            'dob' => $enquiry->dob,
            'address' => $enquiry->address,
            'country' => $enquiry->country,
            'city' => $enquiry->city,
            'state' => $enquiry->state,
            'pincode' => $enquiry->pincode,
            'source' => $enquiry->source,
            'goal' => $enquiry->goal,
        ]);
    }

    protected function afterCreate(): void
    {
        if (! $this->enquiryId) {
            return;
        }

        Enquiry::findOrFail($this->enquiryId)->convertTo($this->record);

        Notification::make()
            ->title(__('app.notifications.member_created'))
            ->body(__('app.notifications.enquiry_converted_to_member'))
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return __('app.actions.new', ['resource' => MemberResource::getModelLabel()]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            MemberResource::getUrl('index') => MemberResource::getNavigationLabel(),
        ];
    }
}
