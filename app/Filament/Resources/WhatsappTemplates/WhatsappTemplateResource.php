<?php

namespace App\Filament\Resources\WhatsappTemplates;

use App\Filament\Resources\WhatsappTemplates\Pages\CreateWhatsappTemplate;
use App\Filament\Resources\WhatsappTemplates\Pages\EditWhatsappTemplate;
use App\Filament\Resources\WhatsappTemplates\Pages\ListWhatsappTemplates;
use App\Filament\Resources\WhatsappTemplates\Schemas\WhatsappTemplateForm;
use App\Filament\Resources\WhatsappTemplates\Tables\WhatsappTemplatesTable;
use App\Models\WhatsappTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WhatsappTemplateResource extends Resource
{
    protected static ?string $model = WhatsappTemplate::class;

    protected static \UnitEnum|string|null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'Template';

    protected static ?string $pluralModelLabel = 'Templates';

    public static function form(Schema $schema): Schema
    {
        return WhatsappTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappTemplates::route('/'),
            'create' => CreateWhatsappTemplate::route('/create'),
            'edit' => EditWhatsappTemplate::route('/{record}/edit'),
        ];
    }
}
