<?php

namespace App\Filament\Resources\CommunicationTemplates\Tables;

use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationAutomationService;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\CommunicationTemplateRenderer;
use App\Services\Communication\EmailDeliveryContext;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommunicationTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('app.communication.fields.event'))
                    ->badge(),
                TextColumn::make('channel')
                    ->label(__('app.communication.fields.channel')),
                TextColumn::make('event_template_status')
                    ->label(__('app.fields.status'))
                    ->state(fn (CommunicationTemplate $record): string => $record->isUsedForEvent()
                        ? __('app.communication.template_in_use')
                        : __('app.communication.template_available'))
                    ->badge()
                    ->color(fn (CommunicationTemplate $record): string => $record->isUsedForEvent() ? 'success' : 'gray'),
                TextColumn::make('delivery_mode')
                    ->label(__('app.communication.fields.delivery'))
                    ->state(fn (CommunicationTemplate $record): ?string => match (true) {
                        ! $record->isUsedForEvent() => null,
                        $record->isAutomaticDeliveryEnabled() => __('app.communication.modes.automatic'),
                        default => __('app.communication.modes.manual'),
                    })
                    ->badge()
                    ->color(fn (CommunicationTemplate $record): string => $record->isAutomaticDeliveryEnabled() ? 'info' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('deliveries_count')
                    ->label(__('app.resources.communication_deliveries.plural'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('app.fields.updated_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading_template_actions')
                            ->label(__('app.communication.template_actions'))
                            ->disabled()
                            ->color('gray'),
                        Action::make('preview_and_test')
                            ->label(__('app.communication.preview_and_test'))
                            ->icon(Heroicon::OutlinedBeaker)
                            ->color('gray')
                            ->modalHeading(__('app.communication.preview_and_test'))
                            ->modalDescription(__('app.communication.preview_help'))
                            ->modalSubmitActionLabel(__('app.communication.send_test'))
                            ->modalWidth(Width::ThreeExtraLarge)
                            ->fillForm(fn (): array => [
                                'recipient' => auth()->user()?->email,
                            ])
                            ->schema([
                                Section::make(__('app.communication.preview'))
                                    ->schema([
                                        TextEntry::make('preview_subject')
                                            ->label(__('app.communication.fields.subject'))
                                            ->state(fn (CommunicationTemplate $record, CommunicationTemplateRenderer $renderer): string => __('app.communication.test_subject', [
                                                'subject' => $renderer->render(
                                                    $record,
                                                    $record->eventKey()?->sampleVariables() ?? [],
                                                )['subject'],
                                            ])),
                                        TextEntry::make('preview_body')
                                            ->label(__('app.communication.fields.body'))
                                            ->state(fn (CommunicationTemplate $record, CommunicationTemplateRenderer $renderer): string => $renderer->render(
                                                $record,
                                                $record->eventKey()?->sampleVariables() ?? [],
                                            )['body'])
                                            ->markdown(),
                                    ]),
                                TextInput::make('recipient')
                                    ->label(__('app.communication.fields.test_recipient'))
                                    ->email()
                                    ->required(),
                            ])
                            ->authorize(fn (CommunicationTemplate $record): bool => auth()->user()?->can('update', $record) ?? false)
                            ->visible(fn (CommunicationTemplate $record): bool => $record->eventKey() !== null)
                            ->action(function (
                                CommunicationTemplate $record,
                                array $data,
                                CommunicationService $communicationService,
                                EmailDeliveryContext $emailContext,
                            ): void {
                                if (! $emailContext->isDeliveryConfigured()) {
                                    Notification::make()
                                        ->title(__('app.notifications.failed'))
                                        ->body($emailContext->unavailableReason())
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $recipient = (string) $data['recipient'];

                                $communicationService->queue(
                                    templateKey: $record->key,
                                    recipient: $recipient,
                                    variables: $record->eventKey()?->sampleVariables() ?? [],
                                    actorId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
                                    isTest: true,
                                );

                                Notification::make()
                                    ->title(__('app.communication.test_delivery_queued'))
                                    ->body(__('app.communication.test_delivery_queued_for', ['email' => $recipient]))
                                    ->success()
                                    ->send();
                            }),
                        Action::make('use_for_event')
                            ->label(__('app.communication.use_for_event'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalDescription(fn (CommunicationTemplate $record): string => __('app.communication.use_for_event_confirmation', [
                                'event' => $record->eventKey()?->getLabel(),
                            ]))
                            ->authorize(fn (CommunicationTemplate $record): bool => auth()->user()?->can('update', $record) ?? false)
                            ->visible(fn (CommunicationTemplate $record): bool => ! $record->isUsedForEvent())
                            ->action(function (CommunicationTemplate $record, CommunicationAutomationService $automationService): void {
                                $automationService->useTemplate($record);

                                Notification::make()
                                    ->title(__('app.communication.template_selected'))
                                    ->success()
                                    ->send();
                            }),
                        Action::make('delivery_settings')
                            ->label(__('app.communication.delivery_settings'))
                            ->icon(Heroicon::OutlinedBolt)
                            ->color('gray')
                            ->modalWidth(Width::Small)
                            ->modalSubmitActionLabel(__('app.actions.save'))
                            ->fillForm(fn (CommunicationTemplate $record): array => [
                                'send_automatically' => $record->isAutomaticDeliveryEnabled(),
                            ])
                            ->schema([
                                Toggle::make('send_automatically')
                                    ->label(__('app.communication.send_automatically'))
                                    ->helperText(__('app.communication.automatic_delivery_help')),
                            ])
                            ->authorize(fn (CommunicationTemplate $record): bool => auth()->user()?->can('update', $record) ?? false)
                            ->visible(fn (CommunicationTemplate $record): bool => $record->isUsedForEvent()
                                && ($record->eventKey()?->isAutomatic() ?? false))
                            ->action(function (CommunicationTemplate $record, array $data, CommunicationAutomationService $automationService): void {
                                $automationService->setAutomaticDelivery($record, (bool) $data['send_automatically']);

                                Notification::make()
                                    ->title(__('app.communication.delivery_settings_saved'))
                                    ->success()
                                    ->send();
                            }),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('heading_record_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        ViewAction::make()
                            ->modalCancelAction(false)
                            ->modalWidth(Width::ThreeExtraLarge),
                        EditAction::make(),
                        DeleteAction::make()
                            ->visible(fn (CommunicationTemplate $record): bool => $record->canBeDeleted()),
                    ])->dropdown(false),
                ])
                    ->label(__('app.actions.record_actions'))
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->dropdown(),
            ])
            ->recordAction('view')
            ->recordUrl(null)
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading(__('app.communication.empty_templates'));
    }
}
