<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Tables\InvoiceTableActions;
use App\Models\Invoice;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property-read Invoice $record
 */
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return __('app.titles.invoice_number', ['number' => $this->record->number]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::edit()
                ->hidden(fn (): bool => $this->record->status?->value !== 'issued'),
            InvoiceTableActions::forViewPage(),
        ];
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
