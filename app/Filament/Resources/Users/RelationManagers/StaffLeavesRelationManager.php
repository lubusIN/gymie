<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\StaffLeaves\StaffLeaveResource;
use App\Filament\Resources\StaffLeaves\Tables\StaffLeavesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffLeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'staffLeaves';

    protected static ?string $relatedResource = StaffLeaveResource::class;

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.resources.staff_leaves.plural');
    }

    public function table(Table $table): Table
    {
        return StaffLeavesTable::configure($table, showStaff: false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['reviewedBy', 'cancelledBy']));
    }
}
