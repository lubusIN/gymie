<?php

namespace App\Filament\Resources\CommunicationDeliveries\Tables;

use App\Enums\CommunicationDeliveryStatus;
use App\Models\CommunicationDelivery;
use App\Services\Communication\EmailDeliveryContext;
use App\Services\Communication\RetryCommunicationDelivery;
use App\Support\Filament\CommunicationRelatedRecord;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommunicationDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.name')
                    ->label(__('app.communication.fields.template'))
                    ->description(fn (CommunicationDelivery $record): ?string => $record->is_test
                        ? __('app.communication.test_delivery')
                        : null),
                TextColumn::make('recipient')
                    ->label(__('app.communication.fields.recipient'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
                TextColumn::make('related')
                    ->label(__('app.communication.fields.related'))
                    ->state(fn (CommunicationDelivery $record): string => CommunicationRelatedRecord::label($record))
                    ->url(fn (CommunicationDelivery $record): ?string => CommunicationRelatedRecord::url($record)),
                TextColumn::make('actor.name')
                    ->label(__('app.communication.fields.actor'))
                    ->placeholder(__('app.placeholders.system')),
                TextColumn::make('queued_at')
                    ->label(__('app.communication.fields.queued_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label(__('app.communication.fields.sent_at'))
                    ->dateTime('d M Y, h:i A')
                    ->placeholder(__('app.placeholders.na')),
            ])
            ->filters([
                SelectFilter::make('communication_template_id')
                    ->label(__('app.communication.fields.template'))
                    ->relationship('template', 'name'),
                SelectFilter::make('status')
                    ->label(__('app.fields.status'))
                    ->options(collect(CommunicationDeliveryStatus::cases())
                        ->mapWithKeys(fn (CommunicationDeliveryStatus $status): array => [$status->value => $status->getLabel()])
                        ->all()),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.communication.delivery_actions'),
                    primaryActions: [
                        Action::make('retry')
                            ->label(__('app.actions.retry'))
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalIcon(Heroicon::OutlinedArrowPath)
                            ->modalDescription(__('app.communication.retry_confirmation'))
                            ->authorize(fn (CommunicationDelivery $record): bool => auth()->user()?->can('update', $record) ?? false)
                            ->visible(fn (CommunicationDelivery $record): bool => $record->canBeRetried())
                            ->disabled(fn (EmailDeliveryContext $emailContext): bool => ! $emailContext->isDeliveryConfigured())
                            ->tooltip(fn (EmailDeliveryContext $emailContext): string => $emailContext->isDeliveryConfigured()
                                ? __('app.actions.retry')
                                : $emailContext->unavailableReason())
                            ->action(function (CommunicationDelivery $record, RetryCommunicationDelivery $retryDelivery): void {
                                $retryDelivery->handle($record);

                                Notification::make()
                                    ->title(__('app.communication.delivery_requeued'))
                                    ->success()
                                    ->send();
                            }),
                    ],
                    recordActions: [
                        ViewAction::make()
                            ->modalCancelAction(false)
                            ->modalWidth(Width::ThreeExtraLarge),
                        DeleteAction::make(),
                    ],
                    primaryVisible: fn (CommunicationDelivery $record): bool => $record->canBeRetried()
                        && (auth()->user()?->can('update', $record) ?? false),
                )
                    ->label(__('app.actions.record_actions'))
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->dropdown(),
            ])
            ->recordAction('view')
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-paper-airplane')
            ->emptyStateHeading(__('app.communication.empty_deliveries'));
    }
}
