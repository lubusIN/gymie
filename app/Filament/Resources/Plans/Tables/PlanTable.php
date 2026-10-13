<?php

namespace App\Filament\Resources\Plans\Tables;

use App\Enums\Status;
use App\Helpers\Helpers;
use App\Models\Plan;
use App\Models\Service;
use App\Support\Filament\StatusAction;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PlanTable
{
    /**
     * Configure the plan table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('code')
                    ->searchable()
                    ->label(__('app.fields.code')),
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('app.fields.name')),
                TextColumn::make('description')
                    ->searchable()
                    ->label(__('app.fields.description')),
                TextColumn::make('service.name')
                    ->searchable()
                    ->label(__('app.fields.service')),
                TextColumn::make('days')
                    ->searchable()
                    ->label(__('app.fields.days')),
                TextColumn::make('amount')
                    ->searchable()
                    ->label(__('app.fields.amount'))
                    ->money(Helpers::getCurrencyCode()),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('app.fields.status')),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon(
                ! Service::exists() ? 'heroicon-o-cog-8-tooth' : 'heroicon-o-pencil-square'
            )
            ->emptyStateHeading(function ($livewire): string {
                if (! Service::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.services.plural')]);
                }

                return TableUi::emptyStateHeading($livewire, Plan::class, 'plans');
            })
            ->emptyStateDescription(function ($livewire): string {
                if (! Service::exists()) {
                    return __('app.empty.go_to_services_to_create');
                }

                return TableUi::emptyStateDescription($livewire, Plan::class, 'plans');
            })
            ->emptyStateActions([
                Action::make('manage_service')
                    ->label(__('app.actions.manage_services'))
                    ->url(fn () => route('filament.admin.resources.services.index'))
                    ->icon('heroicon-o-arrow-right')
                    ->iconPosition('after')
                    ->hidden(fn () => Service::exists()),
                CreateAction::make()
                    ->modalAlignment('center')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalHeading(__('app.actions.new', ['resource' => __('app.resources.plans.singular')]))
                    ->createAnother(false)
                    ->visible(fn () => Service::exists() && ! Plan::exists()),
            ])
            ->filters([
                TrashedFilter::make(),
                TableUi::dateRangeFilter(),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.fields.status'),
                    primaryActions: [
                        StatusAction::make('mark_as_active', Status::Active)
                            ->color('success')
                            ->label(__('app.actions.mark_as_active'))
                            ->requiresConfirmation()
                            ->action(fn (Plan $record) => tap($record, function ($record) {
                                $record->update(['status' => 'active']);
                                Notification::make()
                                    ->title(__('app.notifications.plan_activated'))
                                    ->success()
                                    ->send();
                            }))
                            ->visible(fn ($record) => $record->status->value === 'inactive'),
                        StatusAction::make('mark_as_inactive', Status::Inactive)
                            ->color('danger')
                            ->label(__('app.actions.mark_as_inactive'))
                            ->requiresConfirmation()
                            ->action(fn (Plan $record) => tap($record, function ($record) {
                                $record->update(['status' => 'inactive']);
                                Notification::make()
                                    ->title(__('app.notifications.plan_deactivated'))
                                    ->danger()
                                    ->send();
                            }))
                            ->visible(fn ($record) => $record->status->value === 'active'),
                    ],
                    recordActions: [
                        ViewAction::make()
                            ->modalWidth(Width::ExtraLarge)
                            ->modalCancelAction(false)
                            ->modalAlignment('center'),
                        EditAction::make()
                            ->modalAlignment('center')
                            ->modalWidth(Width::ExtraLarge),
                        DeleteAction::make()->hiddenLabel(),
                    ],
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
