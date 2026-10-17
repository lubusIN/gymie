<?php

namespace App\Filament\Resources\StaffLeaves;

use App\Filament\Resources\StaffLeaves\Pages\CreateStaffLeave;
use App\Filament\Resources\StaffLeaves\Pages\EditStaffLeave;
use App\Filament\Resources\StaffLeaves\Pages\ListStaffLeaves;
use App\Filament\Resources\StaffLeaves\Pages\ViewStaffLeave;
use App\Filament\Resources\StaffLeaves\Schemas\StaffLeaveForm;
use App\Filament\Resources\StaffLeaves\Schemas\StaffLeaveInfolist;
use App\Filament\Resources\StaffLeaves\Tables\StaffLeavesTable;
use App\Models\StaffLeave;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffLeaveResource extends Resource
{
    protected static ?string $model = StaffLeave::class;

    public static function getModelLabel(): string
    {
        return __('app.resources.staff_leaves.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.staff_leaves.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['staff.name', 'reason'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var StaffLeave $record */
        return $record->staff->name;
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (! $record instanceof StaffLeave || ! $record->isPending()) {
            return Response::deny();
        }

        return parent::getEditAuthorizationResponse($record);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! $record instanceof StaffLeave || ! $record->isPending()) {
            return Response::deny();
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function form(Schema $schema): Schema
    {
        return StaffLeaveForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StaffLeaveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffLeavesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffLeaves::route('/'),
            'create' => CreateStaffLeave::route('/create'),
            'view' => ViewStaffLeave::route('/{record}'),
            'edit' => EditStaffLeave::route('/{record}/edit'),
        ];
    }

    /** @return Builder<StaffLeave> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['staff', 'reviewedBy', 'cancelledBy']);
        $user = Filament::auth()->user();

        if (! $user || $user->can('ViewAny:StaffLeave')) {
            return $query;
        }

        return $query->where('staff_id', $user->getKey());
    }
}
