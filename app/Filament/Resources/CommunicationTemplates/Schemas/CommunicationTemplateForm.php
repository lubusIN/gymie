<?php

namespace App\Filament\Resources\CommunicationTemplates\Schemas;

use App\Enums\CommunicationTemplateKey;
use App\Models\CommunicationTemplate;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CommunicationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.communication.template_content'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?CommunicationTemplate $record): bool => $record?->isSystem() ?? false)
                            ->dehydrated(fn (?CommunicationTemplate $record): bool => ! ($record?->isSystem() ?? false)),
                        Select::make('event')
                            ->label(__('app.communication.fields.event'))
                            ->options(CommunicationTemplateKey::class)
                            ->required()
                            ->live()
                            ->disabled(fn (?CommunicationTemplate $record): bool => self::isEventLocked($record))
                            ->dehydrated(fn (?CommunicationTemplate $record): bool => ! self::isEventLocked($record)),
                        TextInput::make('channel')
                            ->label(__('app.communication.fields.channel'))
                            ->default('email')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('app.communication.fields.subject'))
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        MarkdownEditor::make('body')
                            ->label(__('app.communication.fields.body'))
                            ->required()
                            ->toolbarButtons([
                                ['bold', 'italic', 'strike', 'link'],
                                ['heading'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->minHeight('14rem')
                            ->maxHeight('28rem')
                            ->columnSpanFull(),
                        TextEntry::make('available_variables')
                            ->label(__('app.communication.fields.variables'))
                            ->state(fn (Get $get): array => collect(self::eventFromState($get('event'))?->variables() ?? [])
                                ->map(fn (string $variable): string => "{{ {$variable} }}")
                                ->all())
                            ->badge()
                            ->placeholder(__('app.communication.select_event_for_variables'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function isEventLocked(?CommunicationTemplate $record): bool
    {
        return $record !== null
            && ($record->isSystem() || $record->automations->isNotEmpty());
    }

    private static function eventFromState(mixed $state): ?CommunicationTemplateKey
    {
        if ($state instanceof CommunicationTemplateKey) {
            return $state;
        }

        return is_string($state) ? CommunicationTemplateKey::tryFrom($state) : null;
    }
}
