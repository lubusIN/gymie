<?php

namespace App\Filament\Resources\StaffAttendances;

use App\Filament\Resources\StaffAttendances\Pages\ListStaffAttendances;
use App\Filament\Resources\StaffAttendances\Tables\StaffAttendancesTable;
use App\Models\StaffAttendance;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** @extends resource<StaffAttendance> */
class StaffAttendanceResource extends Resource
{
    protected static ?string $model = StaffAttendance::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('app.resources.staff_attendance.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.staff_attendance.plural');
    }

    public static function table(Table $table): Table
    {
        return StaffAttendancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffAttendances::route('/'),
        ];
    }

    /** @return Builder<StaffAttendance> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'staff',
            'checkedInBy',
        ]);
    }
}
