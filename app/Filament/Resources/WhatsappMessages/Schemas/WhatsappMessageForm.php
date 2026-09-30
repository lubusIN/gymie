<?php

namespace App\Filament\Resources\WhatsappMessages\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;

class WhatsappMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message Details')
                    ->schema([
                        TextInput::make('recipient')
                            ->required()
                            ->placeholder('9198XXXXXXXX')
                            ->helperText('Store in international format without formatting characters.'),
                        Select::make('template_name')
                            ->required()
                            ->options(function () {
                                return \App\Models\WhatsappTemplate::pluck('name', 'name');
                            }),
                        Select::make('language')
                            ->required()
                            ->options([
                                'en_US' => 'English (US)',
                                'en_GB' => 'English (UK)',
                                'hi' => 'Hindi',
                            ])
                            ->default('en_US'),
                        Repeater::make('parameters')
                            ->schema([
                                TextInput::make('value')->required()->label('Parameter Value'),
                            ])
                            ->label('Template Parameters')
                            ->helperText('Enter values in the order they appear in the template (e.g. {{1}}, {{2}}).'),
                    ]),
            ]);
    }
}
