<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\StaffAttendances\Tables\StaffAttendancesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only staff attendance history embedded in a staff profile.
 */
class StaffAttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'staffAttendances';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.resources.attendance.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return StaffAttendancesTable::configure($table, showStaff: false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('checkedInBy'));
    }
}
