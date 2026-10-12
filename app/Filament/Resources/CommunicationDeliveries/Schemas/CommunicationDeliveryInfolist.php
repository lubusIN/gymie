<?php

namespace App\Filament\Resources\CommunicationDeliveries\Schemas;

use App\Models\CommunicationDelivery;
use App\Support\Filament\CommunicationRelatedRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunicationDeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.communication.delivery_details'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('template.name')->label(__('app.communication.fields.template')),
                        TextEntry::make('status')
                            ->label(__('app.fields.status'))
                            ->badge(),
                        TextEntry::make('channel')
                            ->label(__('app.communication.fields.channel'))
                            ->badge(),
                        TextEntry::make('is_test')
                            ->label(__('app.communication.fields.delivery_type'))
                            ->state(__('app.communication.test_delivery'))
                            ->badge()
                            ->color('info')
                            ->visible(fn (CommunicationDelivery $record): bool => $record->is_test),
                        TextEntry::make('recipient')
                            ->label(__('app.communication.fields.recipient'))
                            ->copyable(),
                        TextEntry::make('actor.name')
                            ->label(__('app.communication.fields.actor'))
                            ->placeholder(__('app.placeholders.system')),
                        TextEntry::make('related')
                            ->label(__('app.communication.fields.related'))
                            ->state(fn (CommunicationDelivery $record): string => CommunicationRelatedRecord::label($record))
                            ->url(fn (CommunicationDelivery $record): ?string => CommunicationRelatedRecord::url($record)),
                        TextEntry::make('subject')
                            ->label(__('app.communication.fields.subject'))
                            ->placeholder(__('app.placeholders.na'))
                            ->columnSpanFull(),
                        TextEntry::make('queued_at')
                            ->label(__('app.communication.fields.queued_at'))
                            ->dateTime('d M Y, h:i A'),
                        TextEntry::make('sent_at')
                            ->label(__('app.communication.fields.sent_at'))
                            ->dateTime('d M Y, h:i A')
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('failed_at')
                            ->label(__('app.communication.fields.failed_at'))
                            ->dateTime('d M Y, h:i A')
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('failure_reason')
                            ->label(__('app.communication.fields.failure_reason'))
                            ->placeholder(__('app.placeholders.na'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
