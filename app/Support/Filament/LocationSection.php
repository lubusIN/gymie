<?php

namespace App\Support\Filament;

use App\Helpers\Helpers;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class LocationSection
{
    /**
     * Build the shared location fields while preserving resource-specific layout and validation.
     */
    public static function make(
        bool $pincodeRequired = false,
        int $sectionColumns = 1,
        int $fieldGroupColumns = 4,
        ?int $addressRows = null,
        bool $countrySearchable = false,
        bool $countryPreloaded = false,
        bool $regionSearchable = false,
    ): Section {
        return Section::make(__('app.ui.location'))
            ->columns($sectionColumns)
            ->schema([
                Textarea::make('address')
                    ->label(__('app.fields.address'))
                    ->required()
                    ->rows($addressRows)
                    ->placeholder(__('app.placeholders.address_example')),
                Group::make()
                    ->columns($fieldGroupColumns)
                    ->schema([
                        Select::make('country')
                            ->label(__('app.fields.country'))
                            ->placeholder(__('app.placeholders.select_country'))
                            ->options(Helpers::getCountries())
                            ->searchable($countrySearchable)
                            ->preload($countryPreloaded)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('state', null);
                                $set('city', null);
                            }),
                        Select::make('state')
                            ->label(__('app.fields.state'))
                            ->placeholder(__('app.placeholders.select_state'))
                            ->options(fn(Get $get): array => Helpers::getStates($get->string('country', isNullable: true)))
                            ->searchable($regionSearchable)
                            ->live(),
                        Select::make('city')
                            ->label(__('app.fields.city'))
                            ->placeholder(__('app.placeholders.select_city'))
                            ->options(fn(Get $get): array => Helpers::getCities($get->string('state', isNullable: true)))
                            ->searchable($regionSearchable)
                            ->live(),
                        TextInput::make('pincode')
                            ->label(__('app.fields.pincode'))
                            ->required($pincodeRequired)
                            ->placeholder(__('app.placeholders.pincode')),
                    ]),
            ]);
    }
}
