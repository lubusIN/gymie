<?php

namespace App\Filament\Widgets\Analytics;

use App\Helpers\Helpers;
use App\Models\InvoiceTransaction;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent invoice transactions table for the selected dashboard range.
 *
 * This helps users quickly verify payments/refunds without leaving the dashboard.
 */
class RecentTransactionsTableWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -35;

    protected static ?string $heading = null;

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->columnManager(false)
            ->paginated(false)
            ->heading(__('app.widgets.recent_transactions'))
            ->query(function (): Builder {
                $range = AnalyticsDateRange::fromFilters($this->pageFilters);

                return InvoiceTransaction::query()
                    ->with(['invoice'])
                    ->whereBetween('occurred_at', [$range->start, $range->end])
                    ->latest('occurred_at')
                    ->limit(5);
            })
            ->columns([
                TextColumn::make('invoice.number')
                    ->label(__('app.resources.invoices.singular'))
                    ->copyable()
                    ->wrap(),
                TextColumn::make('occurred_at')
                    ->label(__('app.fields.date'))
                    ->state(fn (InvoiceTransaction $record): string => $record->occurred_at?->timezone(AppConfig::timezone())->translatedFormat('d M Y') ?? '—')
                    ->description(fn (InvoiceTransaction $record): string => $record->occurred_at?->timezone(AppConfig::timezone())->format('h:i A') ?? '—')
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'payment' => __('app.transactions.payment'),
                        'refund' => __('app.transactions.refund'),
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'payment' => 'success',
                        'refund' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->alignRight()
                    ->state(fn (InvoiceTransaction $record): string => ($record->type === 'refund' ? '-' : '').Helpers::formatCurrency((float) $record->amount)),
                TextColumn::make('payment_method')
                    ->label(__('app.fields.method'))
                    ->formatStateUsing(fn (?string $state): string => PaymentMethod::channelLabel($state)),
            ]);
    }
}
