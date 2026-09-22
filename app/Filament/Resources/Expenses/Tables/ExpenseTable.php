<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\Status;
use App\Filament\Resources\Expenses\Schemas\ExpenseInfolist;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Support\Filament\StatusAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.resources.expenses.singular'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label(__('app.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->numeric(decimalPlaces: 2)
                    ->prefix(Helpers::getCurrencySymbol())
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('app.fields.category'))
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => Helpers::getExpenseCategoryLabel($state) ?? __('app.placeholders.na')),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (Status $state): string => $state->getColor()),
                TextColumn::make('vendor')
                    ->label(__('app.fields.vendor'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')
                    ->label(__('app.fields.due_date'))
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label(__('app.fields.paid_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('app.fields.created_at'))
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading_status')
                            ->label(__('app.fields.status'))
                            ->disabled()
                            ->color('gray'),
                        self::statusAction(Status::Paid, [Status::Pending, Status::Overdue]),
                        self::statusAction(Status::Overdue, [Status::Pending]),
                        self::statusAction(Status::Pending, [Status::Overdue]),
                        self::statusAction(Status::Cancelled, [Status::Pending, Status::Overdue]),
                    ])
                        ->dropdown(false)
                        ->visible(fn (Expense $record): bool => in_array($record->status, [Status::Pending, Status::Overdue], true)),
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        ViewAction::make()
                            ->modal()
                            ->url(null)
                            ->modalCancelAction(false)
                            ->modalAlignment('center')
                            ->modalWidth(Width::ScreenSmall)
                            ->schema(fn ($livewire, Expense $record): array => ExpenseInfolist::configure(
                                Schema::make($livewire)->model($record)->record($record),
                            )->getComponents(withActions: false)),
                        EditAction::make()
                            ->modalHeading(__('app.actions.edit', ['resource' => __('app.resources.expenses.singular')]))
                            ->modalWidth(Width::ScreenLarge)
                            ->closeModalByClickingAway(false),
                        DeleteAction::make(),
                    ])->dropdown(false),
                ])
                    ->label(__('app.actions.record_actions'))
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->dropdown(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.expenses.plural')]))
            ->emptyStateDescription(__('app.empty.create_to_get_started', ['resource' => __('app.resources.expenses.singular')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('app.actions.new', ['resource' => __('app.resources.expenses.singular')]))
                    ->icon('heroicon-m-plus')
                    ->modalHeading(__('app.actions.new', ['resource' => __('app.resources.expenses.singular')]))
                    ->createAnother(false)
                    ->modalWidth(Width::ScreenLarge)
                    ->closeModalByClickingAway(false)
                    ->visible(fn () => ! Expense::exists()),
            ]);
    }

    /**
     * @param  list<Status>  $fromStatuses
     */
    private static function statusAction(Status $targetStatus, array $fromStatuses): Action
    {
        return StatusAction::make('mark_as_'.$targetStatus->value, $targetStatus)
            ->label(__('app.actions.mark_as_status', ['status' => $targetStatus->getLabel()]))
            ->color($targetStatus->getColor())
            ->requiresConfirmation()
            ->authorize(fn (Expense $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Expense $record) use ($targetStatus): void {
                $record->update([
                    'status' => $targetStatus,
                    'paid_at' => $targetStatus === Status::Paid ? now() : null,
                ]);

                Notification::make()
                    ->title(__('app.notifications.expense_marked_as', ['status' => $targetStatus->getLabel()]))
                    ->success()
                    ->send();
            })
            ->visible(fn (Expense $record): bool => in_array($record->status, $fromStatuses, true));
    }
}
