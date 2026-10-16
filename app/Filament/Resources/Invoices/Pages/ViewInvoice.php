<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Tables\InvoiceTableActions;
use App\Models\Invoice;
use App\Support\Filament\HasStatusHeading;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property-read Invoice $record
 */
class ViewInvoice extends ViewRecord
{
    use HasStatusHeading;

    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return InvoiceResource::getModelLabel().' #'.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return InvoiceTableActions::forViewPage();
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.billing'),
            InvoiceResource::getUrl('index') => InvoiceResource::getNavigationLabel(),
            (string) $this->record->number,
        ];
    }
}
