<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Support\Filament\TableUi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Shared attendance table configuration for the workspace and member profile.
 */
class AttendanceTable
{
    /**
     * Configure attendance history and optionally include member identity columns.
     */
    public static function configure(Table $table, bool $showMember = true): Table
    {
        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('member.code')
                    ->label(__('app.fields.member_code'))
                    ->searchable()
                    ->visible($showMember),
                TextColumn::make('member.name')
                    ->label(__('app.resources.members.singular'))
                    ->searchable()
                    ->visible($showMember),
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
                ...($showMember ? [] : [TableUi::dateRangeFilter('visited_on')]),
                SelectFilter::make('member')
                    ->relationship('member', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($showMember),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading(__('app.attendance.no_visits'))
            ->emptyStateDescription(__('app.attendance.no_visits_description'));
    }
}
