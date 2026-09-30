<?php

namespace App\Filament\Resources\WhatsappTemplates\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;

class WhatsappTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Use lowercase alphanumeric characters and underscores only (e.g. hello_world).'),
                        Select::make('category')
                            ->required()
                            ->options([
                                'UTILITY' => 'Utility',
                                'MARKETING' => 'Marketing',
                                'AUTHENTICATION' => 'Authentication',
                            ]),
                        Select::make('language')
                            ->required()
                            ->options([
                                'en_US' => 'English (US)',
                                'en_GB' => 'English (UK)',
                                'hi' => 'Hindi',
                            ])
                            ->default('en_US'),
                        Textarea::make('body')
                            ->required()
                            ->rows(4)
                            ->helperText('Support variable placeholders such as: Hi {{1}}, your membership expires on {{2}}.'),
                        Repeater::make('example_values')
                            ->schema([
                                TextInput::make('value')->required()->label('Example Value'),
                            ])
                            ->label('Example Values')
                            ->helperText('Enter an example value for each placeholder in the body text in order.'),
                    ]),
            ]);
    }
}
