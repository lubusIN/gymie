<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\Status;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\Reporting\CsvDownloadService;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label(__('app.actions.export_csv'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function getTabs(): array
    {
        return TableUi::statusTabs(Invoice::class, [
            Status::Issued,
            Status::Partial,
            Status::Overdue,
            Status::Paid,
            Status::Refund,
            Status::Cancelled,
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.billing'),
            InvoiceResource::getUrl('index') => InvoiceResource::getNavigationLabel(),
        ];
    }

    /**
     * Export the invoices represented by the active table state.
     */
    private function exportCsv(): StreamedResponse
    {
        $query = $this->getTableQueryForExport()
            ->with(['subscription.member', 'subscription.plan']);

        return app(CsvDownloadService::class)->download(
            query: $query,
            filename: 'invoices-'.now()->toDateString().'.csv',
            headings: [
                __('app.fields.invoice_number'),
                __('app.fields.member'),
                __('app.fields.plan'),
                __('app.fields.invoice_date'),
                __('app.fields.due_date'),
                __('app.fields.status'),
                __('app.invoices.pdf.subtotal'),
                __('app.fields.discount'),
                __('app.fields.tax'),
                __('app.fields.total'),
                __('app.fields.paid'),
                __('app.fields.due'),
            ],
            row: fn (Invoice $invoice): array => [
                $invoice->number,
                $invoice->subscription?->member?->name,
                $invoice->subscription?->plan?->name,
                $invoice->date?->toDateString(),
                $invoice->due_date?->toDateString(),
                $invoice->status?->getLabel(),
                (float) $invoice->subscription_fee,
                (float) $invoice->discount_amount,
                (float) $invoice->tax,
                (float) $invoice->total_amount,
                (float) $invoice->paid_amount,
                (float) $invoice->due_amount,
            ],
        );
    }
}
