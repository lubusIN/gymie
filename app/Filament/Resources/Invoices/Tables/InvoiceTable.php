<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\Status;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Subscription;
use App\Services\Email\InvoiceEmailService;
use App\Services\Invoices\InvoiceTransactionService;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use App\Support\Filament\StatusAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class InvoiceTable
{
    /**
     * Configure the invoice table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('number')
                    ->label(__('app.fields.invoice_number'))
                    ->sortable(),
                TextColumn::make('subscription.member.name')
                    ->label(__('app.fields.subscription'))
                    ->description(fn ($record): string => $record->subscription->member->code),
                TextColumn::make('date')
                    ->label(__('app.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('app.fields.due_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('subscription_fee')
                    ->label(__('app.fields.fee'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('paid_amount')
                    ->label(__('app.fields.paid'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('tax')
                    ->label(__('app.fields.tax'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('total_amount')
                    ->label(__('app.fields.total'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('due_amount')
                    ->label(__('app.fields.due'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')->label(__('app.fields.date_from')),
                        DatePicker::make('date_to')->label(__('app.fields.date_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->where('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->where('date', '<=', $date),
                            );
                    }),
            ])
            ->emptyStateIcon(
                ! Subscription::exists()
                    ? 'heroicon-o-ticket'
                    : 'heroicon-o-document-text'
            )
            ->emptyStateHeading(function ($livewire): string {
                // If no subscription exist
                if (! Subscription::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.subscriptions.plural')]);
                }

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $tab = $livewire->activeTab;
                $records = (string) __('app.resources.invoices.plural');
                $statusKey = $tab === 'partial' ? 'partially_paid' : $tab;
                $status = $tab !== 'all' ? (string) __('app.status.'.$statusKey) : null;
                $heading = $status
                    ? __('app.empty.no_status_records', ['status' => $status, 'records' => $records])
                    : __('app.empty.no_records', ['records' => $records]);

                if (! $from && ! $to) {
                    return $heading;
                }

                if ($tab === 'all') {
                    return __('app.empty.no_records_in_range', ['records' => $records]);
                }

                return Invoice::where('status', $tab)->exists()
                    ? __('app.empty.no_status_records_in_range', ['status' => $status, 'records' => $records])
                    : $heading;
            })
            ->emptyStateDescription(function ($livewire): string {
                // If no subscriptions exist
                if (! Subscription::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.subscriptions.singular')]);
                }

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $tab = $livewire->activeTab;
                $records = (string) __('app.resources.invoices.plural');
                $record = (string) __('app.resources.invoices.singular');
                $statusKey = $tab === 'partial' ? 'partially_paid' : $tab;
                $status = $tab !== 'all' ? (string) __('app.status.'.$statusKey) : null;

                if (! $fromRaw && ! $toRaw) {
                    return $status
                        ? __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status])
                        : __('app.empty.create_to_get_started', ['resource' => $record]);
                }

                $from = $fromRaw ? Carbon::parse($fromRaw)->format('d-m-Y') : (string) __('app.common.the_beginning');
                $to = $toRaw ? Carbon::parse($toRaw)->format('d-m-Y') : (string) __('app.common.today');

                if ($tab === 'all') {
                    return __('app.empty.found_none_between', ['records' => $records, 'from' => $from, 'to' => $to]);
                }

                if (! Invoice::where('status', $tab)->exists()) {
                    return $status
                        ? __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status])
                        : __('app.empty.create_to_get_started', ['resource' => $record]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->emptyStateActions([
                Action::make('create_subscription')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.subscriptions.singular')]))
                    ->url(fn () => route('filament.admin.resources.subscriptions.create'))
                    ->icon('heroicon-o-plus')
                    ->hidden(fn () => Subscription::exists()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading_status')
                            ->label(__('app.actions.manage_invoice'))
                            ->disabled()
                            ->color('gray')
                            ->visible(fn (Invoice $record): bool => ! in_array($record->status?->value, ['refund', 'cancelled'], true)),
                        Action::make('add_payment')
                            ->label(__('app.actions.add_payment'))
                            ->color('info')
                            ->icon('heroicon-s-banknotes')
                            ->modalWidth('md')
                            ->schema([
                                TextInput::make('amount')
                                    ->label(__('app.fields.amount_with_currency', ['currency' => Helpers::getCurrencyCode()]))
                                    ->required()
                                    ->numeric()
                                    ->reactive()
                                    ->default(fn (Invoice $record): float => (float) ($record->due_amount ?? 0))
                                    ->placeholder(__('app.placeholders.enter_amount'))
                                    ->validationAttribute('amount')
                                    ->helperText(fn (Invoice $record): string => __('app.help.due_amount', ['amount' => Helpers::formatCurrency($record->due_amount)]))
                                    ->maxValue(fn (Invoice $record): float => max((float) $record->due_amount, 0))
                                    ->minValue(0.01)
                                    ->afterStateUpdated(function ($livewire, TextInput $component) {
                                        $livewire->validateOnly($component->getStatePath());
                                    }),
                                DateTimePicker::make('occurred_at')
                                    ->label(__('app.fields.paid_at'))
                                    ->seconds(false)
                                    ->timezone(AppConfig::timezone())
                                    ->default(fn (): string => now()->timezone(AppConfig::timezone())->format('Y-m-d H:i:s'))
                                    ->required(),
                                Select::make('payment_method')
                                    ->label(__('app.fields.payment_method'))
                                    ->options(PaymentMethod::options())
                                    ->default(fn (Invoice $record): string => $record->payment_method ?: 'cash')
                                    ->nullable(),
                                Textarea::make('note')
                                    ->label(__('app.fields.note'))
                                    ->rows(2)
                                    ->placeholder(__('app.placeholders.optional_note')),
                            ])
                            ->action(function (Invoice $record, array $data) {
                                $amount = (float) ($data['amount'] ?? 0);
                                $amount = min(max($amount, 0), (float) ($record->due_amount ?? 0));

                                if ($amount <= 0) {
                                    Notification::make()
                                        ->title(__('app.notifications.invalid_payment_amount'))
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                app(InvoiceTransactionService::class)->record($record, [
                                    'type' => 'payment',
                                    'amount' => $amount,
                                    'occurred_at' => $data['occurred_at'] ?? now()->timezone(AppConfig::timezone()),
                                    'payment_method' => $data['payment_method'] ?? null,
                                    'note' => $data['note'] ?? null,
                                    'created_by' => auth()->id(),
                                ]);

                                $record->refresh();

                                $paidLabel = Helpers::formatCurrency($record->paid_amount);

                                Notification::make()
                                    ->title($record->status?->value === 'paid' ? __('app.notifications.invoice_paid') : __('app.notifications.payment_added'))
                                    ->success()
                                    ->body(__('app.notifications.invoice_paid_total', ['number' => $record->number, 'amount' => $paidLabel]))
                                    ->send();
                            })
                            ->visible(fn (Invoice $record): bool => in_array($record->status?->value, ['issued', 'overdue', 'partial'], true) && (float) $record->due_amount > 0),
                        Action::make('refund')
                            ->label(__('app.actions.refund'))
                            ->color('warning')
                            ->icon('heroicon-s-arrow-path')
                            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
                            ->modalWidth('md')
                            ->schema([
                                TextEntry::make('refund_amount')
                                    ->label(__('app.fields.refund_amount_with_currency', ['currency' => Helpers::getCurrencyCode()]))
                                    ->state(fn (Invoice $record): string => Helpers::formatCurrency((float) $record->paid_amount)),
                                DateTimePicker::make('occurred_at')
                                    ->label(__('app.fields.refunded_at'))
                                    ->seconds(false)
                                    ->timezone(AppConfig::timezone())
                                    ->default(fn (): string => now()->timezone(AppConfig::timezone())->format('Y-m-d H:i:s'))
                                    ->required(),
                                Textarea::make('note')
                                    ->label(__('app.fields.note'))
                                    ->rows(2)
                                    ->placeholder(__('app.placeholders.optional_note')),
                            ])
                            ->action(function (Invoice $record, array $data) {
                                $refund = app(InvoiceTransactionService::class)->refundPaidBalance(
                                    $record,
                                    [
                                        'occurred_at' => $data['occurred_at'] ?? now()->timezone(AppConfig::timezone()),
                                        'note' => $data['note'] ?? null,
                                        'created_by' => auth()->id(),
                                    ],
                                );

                                if (! $refund) {
                                    Notification::make()
                                        ->title(__('app.notifications.invalid_refund_amount'))
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $record->refresh();

                                Notification::make()
                                    ->title(__('app.notifications.invoice_refunded'))
                                    ->warning()
                                    ->body(__('app.notifications.invoice_refunded_body', ['number' => $record->number]))
                                    ->send();
                            })
                            ->visible(fn (Invoice $record): bool => (float) $record->paid_amount > 0 && ! in_array($record->status?->value, ['refund', 'cancelled'], true)),
                        StatusAction::make('cancel_invoice', Status::Cancelled)
                            ->label(__('app.actions.cancel'))
                            ->color('danger')
                            ->action(fn (Invoice $record) => tap($record, function ($record) {
                                if ($record->transactions()->where('type', 'payment')->exists()) {
                                    Notification::make()
                                        ->title(__('app.notifications.cannot_cancel'))
                                        ->danger()
                                        ->body(__('app.notifications.cannot_cancel_body', ['number' => $record->number]))
                                        ->send();

                                    return;
                                }

                                $record->update(['status' => 'cancelled']);
                                Notification::make()
                                    ->title(__('app.notifications.invoice_cancelled'))
                                    ->danger()
                                    ->body(__('app.notifications.invoice_cancelled_body', ['number' => $record->number]))
                                    ->send();
                            }))
                            ->visible(fn (Invoice $record): bool => ! in_array($record->status?->value, ['cancelled', 'refund'], true) && ! $record->transactions()->where('type', 'payment')->exists()),
                    ])
                        ->dropdown(false),

                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        Action::make('email_invoice')
                            ->label(__('app.actions.email_invoice'))
                            ->icon('heroicon-o-envelope')
                            ->color('info')
                            ->modalWidth('md')
                            ->modalSubmitActionLabel(__('app.actions.send'))
                            ->schema([
                                TextInput::make('to_email')
                                    ->label(__('app.fields.to'))
                                    ->email()
                                    ->required()
                                    ->default(fn (Invoice $record): string => (string) ($record->subscription->member->email ?? '')),
                                Textarea::make('note')
                                    ->label(__('app.fields.note'))
                                    ->rows(2)
                                    ->placeholder(__('app.placeholders.optional_note')),
                            ])
                            ->action(function (Invoice $record, array $data): void {
                                app(InvoiceEmailService::class)->queueInvoiceIssuedEmail(
                                    invoiceId: Data::int($record->getKey()),
                                    toEmail: Data::string($data['to_email'] ?? ''),
                                    note: $data['note'] ?? null,
                                    actorId: is_int(auth()->id()) ? auth()->id() : null,
                                );

                                Notification::make()
                                    ->title(__('app.notifications.email_queued'))
                                    ->body(__('app.notifications.invoice_email_queued_to', ['email' => $data['to_email']]))
                                    ->success()
                                    ->send();
                            })
                            ->disabled(function (Invoice $record): bool {
                                $email = (string) ($record->subscription->member->email ?? '');

                                return ! filled($email) || ! filled($record->number) || (float) ($record->total_amount ?? 0) <= 0;
                            })
                            ->tooltip(function (Invoice $record): ?string {
                                if (! filled($record->subscription->member->email ?? null)) {
                                    return __('app.tooltips.member_email_missing');
                                }

                                if (! filled($record->number) || (float) ($record->total_amount ?? 0) <= 0) {
                                    return __('app.tooltips.invoice_document_missing');
                                }

                                return null;
                            })
                            ->visible(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false),
                        Action::make('email_receipt')
                            ->label(__('app.actions.email_receipt'))
                            ->icon('heroicon-o-envelope-open')
                            ->color('gray')
                            ->modalWidth('md')
                            ->modalSubmitActionLabel(__('app.actions.send'))
                            ->schema([
                                TextInput::make('to_email')
                                    ->label(__('app.fields.to'))
                                    ->email()
                                    ->required()
                                    ->default(fn (Invoice $record): string => (string) ($record->subscription->member->email ?? '')),
                                Select::make('payment_transaction_id')
                                    ->label(__('app.fields.payment'))
                                    ->required()
                                    ->options(function (Invoice $record): array {
                                        return InvoiceTransaction::query()
                                            ->where('invoice_id', $record->getKey())
                                            ->where('type', 'payment')
                                            ->latest('occurred_at')
                                            ->limit(5)
                                            ->get()
                                            ->mapWithKeys(function (InvoiceTransaction $transaction): array {
                                                $occurredAt = $transaction->occurred_at?->timezone(AppConfig::timezone())->format('d/m/Y H:i') ?? '—';

                                                return [
                                                    Data::int($transaction->getKey()) => "{$occurredAt} - ".Helpers::formatCurrency((float) ($transaction->amount ?? 0)),
                                                ];
                                            })
                                            ->toArray();
                                    })
                                    ->default(function (Invoice $record): ?int {
                                        return Data::int(InvoiceTransaction::query()
                                            ->where('invoice_id', $record->getKey())
                                            ->where('type', 'payment')
                                            ->latest('occurred_at')
                                            ->value('id'), 0) ?: null;
                                    }),
                                Textarea::make('note')
                                    ->label(__('app.fields.note'))
                                    ->rows(2)
                                    ->placeholder(__('app.placeholders.optional_note')),
                            ])
                            ->action(function (Invoice $record, array $data): void {
                                app(InvoiceEmailService::class)->queuePaymentReceiptEmail(
                                    invoiceId: Data::int($record->getKey()),
                                    transactionId: Data::int($data['payment_transaction_id'] ?? null),
                                    toEmail: Data::string($data['to_email'] ?? ''),
                                    note: $data['note'] ?? null,
                                    actorId: is_int(auth()->id()) ? auth()->id() : null,
                                );

                                Notification::make()
                                    ->title(__('app.notifications.email_queued'))
                                    ->body(__('app.notifications.receipt_email_queued_to', ['email' => $data['to_email']]))
                                    ->success()
                                    ->send();
                            })
                            ->disabled(function (Invoice $record): bool {
                                $email = (string) ($record->subscription->member->email ?? '');

                                return ! filled($email) || ! filled($record->number) || (float) ($record->total_amount ?? 0) <= 0 || (float) ($record->paid_amount ?? 0) <= 0;
                            })
                            ->tooltip(function (Invoice $record): ?string {
                                if (! filled($record->subscription->member->email ?? null)) {
                                    return __('app.tooltips.member_email_missing');
                                }

                                if ((float) ($record->paid_amount ?? 0) <= 0) {
                                    return __('app.tooltips.no_payments_recorded');
                                }

                                if (! filled($record->number) || (float) ($record->total_amount ?? 0) <= 0) {
                                    return __('app.tooltips.invoice_document_missing');
                                }

                                return null;
                            })
                            ->visible(fn (Invoice $record): bool => (auth()->user()?->can('update', $record) ?? false) && (float) ($record->paid_amount ?? 0) > 0),
                        Action::make('preview_invoice')
                            ->label(__('app.actions.view_pdf'))
                            ->icon('heroicon-o-document-text')
                            ->url(fn (Invoice $record): string => route('invoices.preview', $record))
                            ->openUrlInNewTab()
                            ->visible(fn (Invoice $record): bool => auth()->user()?->can('view', $record) ?? false),
                        Action::make('download_invoice')
                            ->label(__('app.actions.download'))
                            ->icon('heroicon-o-arrow-down-tray')
                            ->url(fn (Invoice $record): string => route('invoices.download', $record))
                            ->openUrlInNewTab()
                            ->disabled(fn (Invoice $record): bool => ! filled($record->number) || (float) ($record->total_amount ?? 0) <= 0)
                            ->tooltip(function (Invoice $record): ?string {
                                if (! filled($record->number)) {
                                    return __('app.tooltips.invoice_number_missing');
                                }

                                if ((float) ($record->total_amount ?? 0) <= 0) {
                                    return __('app.tooltips.invoice_total_missing');
                                }

                                return null;
                            })
                            ->visible(fn (Invoice $record): bool => auth()->user()?->can('view', $record) ?? false),
                        ViewAction::make()
                            ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record])),
                        EditAction::make()
                            ->hidden(fn ($record): bool => $record->status?->value !== 'issued')
                            ->url(fn ($record) => InvoiceResource::getUrl('edit', ['record' => $record])),
                    ])->dropdown(false),
                ]),
            ])
            ->toolbarActions([]);
    }
}
