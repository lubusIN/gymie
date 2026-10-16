<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.resources.invoices.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return InvoiceResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return InvoiceResource::table($table)
            ->searchable(false)
            ->filters([])
            ->columnManager(false)
            ->headerActions([]);
    }
}
