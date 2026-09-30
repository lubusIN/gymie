<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\Attendances\Tables\AttendanceTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only attendance history embedded in a member profile.
 */
class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.resources.attendance.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Configure the member-scoped attendance history table.
     */
    public function table(Table $table): Table
    {
        return AttendanceTable::configure($table, showMember: false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('checkedInBy'));
    }
}
