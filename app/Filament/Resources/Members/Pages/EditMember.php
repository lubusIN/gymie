<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read Member $record
 */
class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::view(),
            RecordPageActions::delete(),
            RecordPageActions::forceDelete(),
            RecordPageActions::restore(),
        ];
    }

    public function getTitle(): string
    {
        return __('app.actions.edit', ['resource' => MemberResource::getModelLabel()]);
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
