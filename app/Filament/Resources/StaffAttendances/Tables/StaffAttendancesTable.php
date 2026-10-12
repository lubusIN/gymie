<?php

namespace App\Filament\Resources\StaffAttendances\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->sortable()
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
                Filter::make('visit_date')
                    ->label(__('app.fields.date'))
                    ->schema([
                        DatePicker::make('date_from')->label(__('app.fields.date_from')),
                        DatePicker::make('date_to')->label(__('app.fields.date_to')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['date_from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where('visited_on', '>=', $date),
                        )
                        ->when(
                            $data['date_to'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where('visited_on', '<=', $date),
                        )),
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
