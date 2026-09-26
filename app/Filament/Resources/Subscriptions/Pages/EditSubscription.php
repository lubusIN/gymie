<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Member;
use App\Models\Subscription;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read Subscription $record
 */
class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function getTitle(): string
    {
        return __('app.actions.edit', ['resource' => SubscriptionResource::getModelLabel()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::view(),
            RecordPageActions::delete(),
            RecordPageActions::forceDelete(),
            RecordPageActions::restore(),
        ];
    }

    public function getBreadcrumbs(): array
    {
        $member = $this->record->member;
        assert($member instanceof Member);

        return [
            __('app.navigation.groups.memberships'),
            SubscriptionResource::getUrl('index') => SubscriptionResource::getNavigationLabel(),
            $member->name,
        ];
    }
}
