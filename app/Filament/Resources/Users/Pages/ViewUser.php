<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property-read User $record
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return __('app.titles.record', [
            'resource' => UserResource::getModelLabel(),
            'name' => $this->record->name,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::edit(),
            RecordPageActions::delete(),
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
