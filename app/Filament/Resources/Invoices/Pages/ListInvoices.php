<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\Status;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\Reporting\CsvDownloadService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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
        return [
            'all' => Tab::make(__('app.common.all')),
            'issued' => Tab::make(__('app.status.issued'))
                ->badge(Invoice::query()->where('status', 'issued')->count())
                ->badgeColor(Status::Issued->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'issued')),
            'partial' => Tab::make(__('app.status.partially_paid'))
                ->badge(Invoice::query()->where('status', 'partial')->count())
                ->badgeColor(Status::Partial->getColor())
                ->label(__('app.status.partial'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'partial')),
            'overdue' => Tab::make(__('app.status.overdue'))
                ->badge(Invoice::query()->where('status', 'overdue')->count())
                ->badgeColor(Status::Overdue->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'overdue')),
            'paid' => Tab::make(__('app.status.paid'))
                ->badge(Invoice::query()->where('status', 'paid')->count())
                ->badgeColor(Status::Paid->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'paid')),
            'refund' => Tab::make(__('app.status.refund'))
                ->badge(Invoice::query()->where('status', 'refund')->count())
                ->badgeColor(Status::Refund->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'refund')),
            'cancelled' => Tab::make(__('app.status.cancelled'))
                ->badge(Invoice::query()->where('status', 'cancelled')->count())
                ->badgeColor(Status::Cancelled->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),
        ];
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
