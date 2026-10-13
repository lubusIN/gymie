<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Resources\Invoices\Tables\InvoiceTable;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Support\Filament\GlobalSearchBadge;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/** @extends resource<Invoice> */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getModelLabel(): string
    {
        return __('app.resources.invoices.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.invoices.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'number',
            'subscription.member.name',
            'subscription.member.code',
            'subscription.member.email',
            'subscription.member.contact',
            'subscription.plan.name',
        ];
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->with(['subscription.member', 'subscription.plan']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        assert($record instanceof Invoice);
        $details = [];

        if ($record->subscription?->member?->name) {
            $details[__('app.fields.member')] = $record->subscription->member->name;
        }

        if ($record->date) {
            $details[__('app.fields.invoice_date')] = $record->date->toDateString();
        }

        if ($record->status) {
            $details[__('app.fields.status')] = GlobalSearchBadge::status($record->status);
        }

        if (! is_null($record->total_amount)) {
            $details[__('app.fields.total_amount')] = Helpers::formatCurrency((float) $record->total_amount);
        }

        return $details;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Define the form schema for the resource.
     */
    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    /**
     * Get the Filament table columns for the invoice list view.
     */
    public static function table(Table $table): Table
    {
        return InvoiceTable::configure($table);
    }

    /**
     * Add infolist to the resource.
     */
    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Invoice>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
