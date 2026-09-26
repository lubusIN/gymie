<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Filament\RecordPageActions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property-read Invoice $record
 */
class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return __('app.titles.edit_invoice_number', ['number' => $this->record->number]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPageActions::view(),
            RecordPageActions::delete(),
            RecordPageActions::forceDelete(),
            RecordPageActions::restore(),
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
