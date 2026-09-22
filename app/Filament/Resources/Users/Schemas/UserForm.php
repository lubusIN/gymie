<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Filament\LocationSection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserForm
{
    /**
     * Configure the user form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        FileUpload::make('photo')
                            ->label(__('app.fields.photo'))
                            ->imageEditor()
                            ->preserveFilenames()
                            ->maxSize(1024 * 1024 * 10)
                            ->disk('public')
                            ->directory('images')
                            ->image()
                            ->placeholder(__('app.placeholders.upload_photo'))
                            ->loadingIndicatorPosition('left')
                            ->panelAspectRatio('6:5')
                            ->panelLayout('integrated')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadButtonPosition('left')
                            ->uploadProgressIndicatorPosition('left'),
                        Grid::make()
                            ->columns(3)
                            ->schema([
                                Group::make()
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('app.fields.name'))
                                            ->required()
                                            ->placeholder(__('app.placeholders.example_full_name')),
                                        TextInput::make('email')
                                            ->label(__('app.fields.email'))
                                            ->email()
                                            ->required()
                                            ->placeholder(__('app.placeholders.example_email'))
                                            ->unique(ignorable: fn ($record) => $record)
                                            ->prefixIcon('heroicon-m-envelope'),
                                    ])->columns(2)->columnSpanFull(),
                                TextInput::make('contact')
                                    ->label(__('app.fields.contact'))
                                    ->prefixIcon('heroicon-m-phone')
                                    ->tel()
                                    ->maxLength(20)
                                    ->regex('/^\+?[0-9\s\-\(\)]+$/') // Allows +, digits, spaces, dashes, and parentheses
                                    ->required(),
                                Select::make('gender')
                                    ->label(__('app.fields.gender'))
                                    ->options([
                                        'male' => __('app.options.gender.male'),
                                        'female' => __('app.options.gender.female'),
                                        'other' => __('app.options.gender.other'),
                                    ])
                                    ->required()
                                    ->default('male')
                                    ->selectablePlaceholder(false),
                                DatePicker::make('dob')
                                    ->required()
                                    ->label(__('app.fields.dob')),
                                Select::make('role')
                                    ->label(__('app.fields.role'))
                                    ->relationship('roles', 'name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn ($record): string => Str::headline($record->name)
                                    ),
                                TextInput::make('password')
                                    ->label(__('app.fields.password'))
                                    ->password()
                                    ->hiddenOn(['view'])
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->revealable(),
                                TextInput::make('password_confirmation')
                                    ->label(__('app.fields.password_confirmation'))
                                    ->password()
                                    ->hiddenOn(['view'])
                                    ->revealable()
                                    ->required(fn (callable $get): bool => filled($get('password')))
                                    ->same('password'),
                            ])
                            ->columnSpan(3),
                    ]),
                LocationSection::make(
                    countrySearchable: true,
                    countryPreloaded: true,
                    regionSearchable: true,
                ),
            ]);
    }
}
