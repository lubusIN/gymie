<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\StaffActivities\Schemas\StaffActivityInfolist;
use App\Filament\Resources\StaffActivities\Tables\StaffActivitiesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'staffActivities';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.activity.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return StaffActivityInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return StaffActivitiesTable::configure($table, showStaff: false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('subject'));
    }
}
