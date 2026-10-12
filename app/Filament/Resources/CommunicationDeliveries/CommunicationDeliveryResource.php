<?php

namespace App\Filament\Resources\CommunicationDeliveries;

use App\Filament\Resources\CommunicationDeliveries\Pages\ListCommunicationDeliveries;
use App\Filament\Resources\CommunicationDeliveries\Schemas\CommunicationDeliveryInfolist;
use App\Filament\Resources\CommunicationDeliveries\Tables\CommunicationDeliveriesTable;
use App\Models\CommunicationDelivery;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunicationDeliveryResource extends Resource
{
    protected static ?string $model = CommunicationDelivery::class;

    protected static ?string $recordTitleAttribute = 'recipient';

    public static function getModelLabel(): string
    {
        return __('app.resources.communication_deliveries.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.communication_deliveries.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommunicationDeliveryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunicationDeliveriesTable::configure($table);
    }

    /** @return Builder<CommunicationDelivery> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['template', 'actor', 'related']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunicationDeliveries::route('/'),
        ];
    }
}
