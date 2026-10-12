<?php

namespace App\Filament\Resources\CommunicationTemplates;

use App\Filament\Resources\CommunicationTemplates\Pages\CreateCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplates\Pages\EditCommunicationTemplate;
use App\Filament\Resources\CommunicationTemplates\Pages\ListCommunicationTemplates;
use App\Filament\Resources\CommunicationTemplates\Schemas\CommunicationTemplateForm;
use App\Filament\Resources\CommunicationTemplates\Schemas\CommunicationTemplateInfolist;
use App\Filament\Resources\CommunicationTemplates\Tables\CommunicationTemplatesTable;
use App\Models\CommunicationTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunicationTemplateResource extends Resource
{
    protected static ?string $model = CommunicationTemplate::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('app.resources.communication_templates.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.communication_templates.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return CommunicationTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommunicationTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunicationTemplatesTable::configure($table);
    }

    /** @return Builder<CommunicationTemplate> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['automations', 'eventConfiguration'])
            ->withCount('deliveries');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunicationTemplates::route('/'),
            'create' => CreateCommunicationTemplate::route('/create'),
            'edit' => EditCommunicationTemplate::route('/{record}/edit'),
        ];
    }
}
