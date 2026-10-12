<?php

namespace App\Filament\Resources\CommunicationTemplates\Schemas;

use App\Models\CommunicationTemplate;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunicationTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.communication.template_content'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('app.fields.name')),
                        TextEntry::make('event')
                            ->label(__('app.communication.fields.event')),
                        TextEntry::make('channel')
                            ->label(__('app.communication.fields.channel'))
                            ->badge(),
                        TextEntry::make('subject')
                            ->label(__('app.communication.fields.subject'))
                            ->columnSpanFull(),
                        TextEntry::make('body')
                            ->label(__('app.communication.fields.body'))
                            ->markdown()
                            ->columnSpanFull(),
                        TextEntry::make('event_template_status')
                            ->label(__('app.fields.status'))
                            ->state(fn (CommunicationTemplate $record): string => $record->isUsedForEvent()
                                ? __('app.communication.template_in_use')
                                : __('app.communication.template_available'))
                            ->badge()
                            ->color(fn (CommunicationTemplate $record): string => $record->isUsedForEvent() ? 'success' : 'gray'),
                        TextEntry::make('delivery_mode')
                            ->label(__('app.communication.fields.delivery'))
                            ->state(fn (CommunicationTemplate $record): ?string => match (true) {
                                ! $record->isUsedForEvent() => null,
                                $record->isAutomaticDeliveryEnabled() => __('app.communication.modes.automatic'),
                                default => __('app.communication.modes.manual'),
                            })
                            ->badge()
                            ->color(fn (CommunicationTemplate $record): string => $record->isAutomaticDeliveryEnabled() ? 'info' : 'gray')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
