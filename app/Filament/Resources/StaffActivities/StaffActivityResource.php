<?php

namespace App\Filament\Resources\StaffActivities;

use App\Filament\Resources\StaffActivities\Pages\ListStaffActivities;
use App\Filament\Resources\StaffActivities\Pages\ViewStaffActivity;
use App\Filament\Resources\StaffActivities\Schemas\StaffActivityInfolist;
use App\Filament\Resources\StaffActivities\Tables\StaffActivitiesTable;
use App\Models\StaffActivity;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** @extends resource<StaffActivity> */
class StaffActivityResource extends Resource
{
    protected static ?string $model = StaffActivity::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('app.staff_activity.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.staff_activity.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function infolist(Schema $schema): Schema
    {
        return StaffActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffActivities::route('/'),
            'view' => ViewStaffActivity::route('/{record}'),
        ];
    }

    /** @return Builder<StaffActivity> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->staff()
            ->with(['causer', 'subject']);
    }
}
