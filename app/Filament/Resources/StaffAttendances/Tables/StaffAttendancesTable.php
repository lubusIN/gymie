<?php

namespace App\Filament\Resources\StaffAttendances\Tables;

use App\Support\Filament\TableUi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StaffAttendancesTable
{
    /**
     * Configure staff attendance history and optionally show staff identity.
     */
    public static function configure(Table $table, bool $showStaff = true): Table
    {
        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('staff.name')
                    ->label(__('app.staff_attendance.staff_member'))
                    ->searchable()
                    ->visible($showStaff),
                TextColumn::make('checked_in_at')
                    ->label(__('app.fields.checked_in_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                TextColumn::make('checkedInBy.name')
                    ->label(__('app.fields.checked_in_by'))
                    ->placeholder(__('app.placeholders.na'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                ...($showStaff ? [] : [TableUi::dateRangeFilter('visited_on')]),
                SelectFilter::make('staff')
                    ->relationship('staff', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($showStaff),
            ])
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading(__('app.staff_attendance.no_attendance'))
            ->emptyStateDescription(__('app.staff_attendance.no_attendance_description'));
    }
}
