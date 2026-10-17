<?php

namespace App\Filament\Resources\StaffLeaves\Tables;

use App\Enums\StaffLeaveType;
use App\Enums\Status;
use App\Models\StaffLeave;
use App\Support\Filament\StaffLeaveActions;
use App\Support\Filament\TableUi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StaffLeavesTable
{
    public static function configure(Table $table, bool $showStaff = true): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('staff.name')
                    ->label(__('app.staff_leave.staff_member'))
                    ->searchable()
                    ->visible($showStaff),
                TextColumn::make('type')
                    ->label(__('app.fields.leave_type')),
                TextColumn::make('start_date')
                    ->label(__('app.fields.start_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('app.fields.end_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('duration')
                    ->label(__('app.fields.duration'))
                    ->state(fn (StaffLeave $record): string => trans_choice(
                        'app.staff_leave.days',
                        $record->durationInDays(),
                        ['count' => $record->durationInDays()],
                    )),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (Status $state): string => $state->getColor()),
                TextColumn::make('reviewedBy.name')
                    ->label(__('app.fields.reviewed_by'))
                    ->placeholder(__('app.placeholders.na'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('app.fields.created_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('staff_id')
                    ->label(__('app.staff_leave.staff_member'))
                    ->relationship('staff', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($showStaff),
                SelectFilter::make('type')
                    ->label(__('app.fields.leave_type'))
                    ->options(StaffLeaveType::class),
                TableUi::dateRangeFilter('start_date', 'end_date'),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.staff_leave.manage_request'),
                    primaryActions: [
                        StaffLeaveActions::approve(),
                        StaffLeaveActions::reject(),
                        StaffLeaveActions::cancel(),
                    ],
                    recordActions: [
                        ViewAction::make(),
                        EditAction::make(),
                        DeleteAction::make(),
                    ],
                    primaryVisible: fn (StaffLeave $record): bool => StaffLeaveActions::canManage($record),
                )
                    ->label(__('app.actions.record_actions'))
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->dropdown(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading(__('app.staff_leave.no_requests'))
            ->emptyStateDescription(__('app.staff_leave.no_requests_description'));
    }
}
