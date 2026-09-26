<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return __('app.actions.edit', ['resource' => UserResource::getModelLabel()]);
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
        return [
            __('app.navigation.groups.administration'),
            UserResource::getUrl('index') => UserResource::getNavigationLabel(),
            $this->record->name,
        ];
    }
}
