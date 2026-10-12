<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Support\Filament\MemberCommunicationActions;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property-read Member $record
 */
class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MemberCommunicationActions::sendWelcome(),
            RecordPageActions::edit(),
            RecordPageActions::delete(),
        ];
    }

    public function getTitle(): string
    {
        return __('app.titles.record', [
            'resource' => MemberResource::getModelLabel(),
            'name' => $this->record->name,
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            MemberResource::getUrl('index') => MemberResource::getNavigationLabel(),
            $this->record->name,
        ];
    }
}
