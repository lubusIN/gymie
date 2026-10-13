<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\Status;
use App\Enums\SubscriptionActivityType;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Services\Communication\CommunicationPreferenceService;
use App\Services\Email\InvoiceEmailService;
use App\Services\Invoices\InvoiceTransactionService;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use App\Support\Filament\StatusAction;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

final class InvoiceTableActions
{
    private function __construct() {}

    public static function make(): ActionGroup
    {
        return TableUi::actionGroup(
            primaryLabel: __('app.actions.manage_invoice'),
            primaryActions: self::primaryActions(),
            recordActions: self::recordActions(includeNavigationActions: true),
            primaryVisible: fn (Invoice $record): bool => ! in_array(
                $record->status?->value,
                ['refund', 'cancelled'],
                true,
            ),
        );
    }

    public static function forViewPage(): ActionGroup
    {
        return ActionGroup::make([
            ...self::primaryActions(),
            ...self::recordActions(includeNavigationActions: false),
        ]);
    }

    /** @return list<Action> */
    private static function primaryActions(): array
    {
        return [
            self::addPaymentAction(),
            self::refundAction(),
            self::cancelInvoiceAction(),
        ];
    }

    /** @return list<Action> */
    private static function recordActions(bool $includeNavigationActions): array
    {
        $recordActions = [
            self::sendPaymentReminderAction(),
            self::emailInvoiceAction(),
            self::emailReceiptAction(),
            self::previewInvoiceAction(),
            self::downloadInvoiceAction(),
        ];

        if ($includeNavigationActions) {
            $recordActions[] = ViewAction::make()
                ->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]));
            $recordActions[] = EditAction::make()
                ->hidden(fn (Invoice $record): bool => $record->status?->value !== 'issued')
                ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record]));
        }

        return $recordActions;
    }

    private static function addPaymentAction(): Action
    {
        return Action::make('add_payment')
            ->label(__('app.actions.add_payment'))
            ->color('info')
            ->icon('heroicon-o-banknotes')
            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->modalWidth(Width::Medium)
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
                    ->afterStateUpdated(function ($livewire, TextInput $component): void {
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
            ->action(function (Invoice $record, array $data, InvoiceTransactionService $transactionService): void {
                $amount = min(
                    max((float) ($data['amount'] ?? 0), 0),
                    (float) ($record->due_amount ?? 0),
                );

                if ($amount <= 0) {
                    Notification::make()
                        ->title(__('app.notifications.invalid_payment_amount'))
                        ->danger()
                        ->send();

                    return;
                }

                $transactionService->record($record, [
                    'type' => 'payment',
                    'amount' => $amount,
                    'occurred_at' => $data['occurred_at'] ?? now()->timezone(AppConfig::timezone()),
                    'payment_method' => $data['payment_method'] ?? null,
                    'note' => $data['note'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $record->refresh();

                Notification::make()
                    ->title($record->status?->value === 'paid' ? __('app.notifications.invoice_paid') : __('app.notifications.payment_added'))
                    ->success()
                    ->body(__('app.notifications.invoice_paid_total', [
                        'number' => $record->number,
                        'amount' => Helpers::formatCurrency($record->paid_amount),
                    ]))
                    ->send();
            })
            ->visible(fn (Invoice $record): bool => in_array($record->status?->value, ['issued', 'overdue', 'partial'], true)
                && (float) $record->due_amount > 0);
    }

    private static function refundAction(): Action
    {
        return Action::make('refund')
            ->label(__('app.actions.refund'))
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->modalWidth(Width::Medium)
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
            ->action(function (Invoice $record, array $data, InvoiceTransactionService $transactionService): void {
                $refund = $transactionService->refundPaidBalance($record, [
                    'occurred_at' => $data['occurred_at'] ?? now()->timezone(AppConfig::timezone()),
                    'note' => $data['note'] ?? null,
                    'created_by' => auth()->id(),
                ]);

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
            ->visible(fn (Invoice $record): bool => (float) $record->paid_amount > 0
                && ! in_array($record->status?->value, ['refund', 'cancelled'], true));
    }

    private static function cancelInvoiceAction(): Action
    {
        return StatusAction::make('cancel_invoice', Status::Cancelled)
            ->label(__('app.actions.cancel'))
            ->color('danger')
            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Invoice $record): void {
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
            })
            ->visible(fn (Invoice $record): bool => ! in_array($record->status?->value, ['cancelled', 'refund'], true)
                && ! $record->transactions()->where('type', 'payment')->exists());
    }

    private static function sendPaymentReminderAction(): Action
    {
        return Action::make('send_payment_reminder')
            ->label(__('app.actions.send_payment_reminder'))
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('app.titles.send_payment_reminder'))
            ->modalDescription(__('app.help.payment_reminder_confirmation'))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('app.actions.send_reminder'))
            ->schema([
                TextEntry::make('payment_reminder_member')
                    ->label(__('app.fields.member'))
                    ->state(fn (Invoice $record): string => (string) ($record->subscription->member->name ?? __('app.placeholders.na'))),
                TextEntry::make('payment_reminder_invoice')
                    ->label(__('app.fields.invoice_number'))
                    ->state(fn (Invoice $record): string => (string) ($record->number ?? __('app.placeholders.na'))),
                TextEntry::make('payment_reminder_due')
                    ->label(__('app.fields.due_amount'))
                    ->state(fn (Invoice $record): string => Helpers::formatCurrency((float) ($record->due_amount ?? 0))),
                TextEntry::make('payment_reminder_due_date')
                    ->label(__('app.fields.due_date'))
                    ->state(fn (Invoice $record): string => $record->due_date?->translatedFormat('d M Y') ?? __('app.placeholders.na')),
                TextEntry::make('payment_reminder_recipient')
                    ->label(__('app.fields.to'))
                    ->state(fn (Invoice $record): string => (string) ($record->subscription->member->email ?? __('app.placeholders.na'))),
                Textarea::make('note')
                    ->label(__('app.fields.note'))
                    ->rows(2)
                    ->placeholder(__('app.placeholders.optional_note')),
            ])
            ->action(function (Invoice $record, array $data, InvoiceEmailService $emailService): void {
                $record->loadMissing('subscription.member');
                $email = Data::string($record->subscription?->member?->email);
                $note = Data::nullableString($data['note'] ?? null);

                $emailService->queuePaymentReminderEmail(
                    invoiceId: Data::int($record->getKey()),
                    toEmail: $email,
                    note: $note,
                    actorId: is_int(auth()->id()) ? auth()->id() : null,
                );

                $record->subscription?->recordActivity(
                    SubscriptionActivityType::PaymentReminderQueued,
                    [
                        'invoice' => $record->number,
                        'amount' => (float) ($record->due_amount ?? 0),
                        'due_date' => $record->due_date?->toDateString(),
                        'note' => $note,
                    ],
                );

                Notification::make()
                    ->title(__('app.notifications.payment_reminder_queued'))
                    ->body(__('app.notifications.payment_reminder_queued_to', ['email' => $email]))
                    ->success()
                    ->send();
            })
            ->disabled(fn (Invoice $record, InvoiceEmailService $emailService, CommunicationPreferenceService $preferenceService): bool => self::emailUnavailableReason(
                $record,
                $emailService,
                $preferenceService,
            ) !== null)
            ->tooltip(fn (Invoice $record, InvoiceEmailService $emailService, CommunicationPreferenceService $preferenceService): ?string => self::emailUnavailableReason(
                $record,
                $emailService,
                $preferenceService,
            ))
            ->visible(fn (Invoice $record): bool => (float) ($record->due_amount ?? 0) > 0
                && in_array($record->status?->value, ['issued', 'partial', 'overdue'], true));
    }

    private static function emailInvoiceAction(): Action
    {
        return Action::make('email_invoice')
            ->label(__('app.actions.email_invoice'))
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->modalWidth(Width::Medium)
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
            ->action(function (Invoice $record, array $data, InvoiceEmailService $emailService): void {
                $emailService->queueInvoiceIssuedEmail(
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
            ->disabled(fn (Invoice $record, InvoiceEmailService $emailService, CommunicationPreferenceService $preferenceService): bool => self::emailUnavailableReason(
                $record,
                $emailService,
                $preferenceService,
            ) !== null)
            ->tooltip(fn (Invoice $record, InvoiceEmailService $emailService, CommunicationPreferenceService $preferenceService): ?string => self::emailUnavailableReason(
                $record,
                $emailService,
                $preferenceService,
            ))
            ->visible(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false);
    }

    private static function emailReceiptAction(): Action
    {
        return Action::make('email_receipt')
            ->label(__('app.actions.email_receipt'))
            ->icon('heroicon-o-envelope-open')
            ->color('gray')
            ->authorize(fn (Invoice $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->modalWidth(Width::Medium)
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
            ->action(function (Invoice $record, array $data, InvoiceEmailService $emailService): void {
                $emailService->queuePaymentReceiptEmail(
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
            ->disabled(fn (Invoice $record, InvoiceEmailService $emailService, CommunicationPreferenceService $preferenceService): bool => self::emailUnavailableReason(
                $record,
                $emailService,
                $preferenceService,
                requiresPayment: true,
            ) !== null)
            ->tooltip(fn (Invoice $record, InvoiceEmailService $emailService, CommunicationPreferenceService $preferenceService): ?string => self::emailUnavailableReason(
                $record,
                $emailService,
                $preferenceService,
                requiresPayment: true,
            ))
            ->visible(fn (Invoice $record): bool => (auth()->user()?->can('update', $record) ?? false) && (float) ($record->paid_amount ?? 0) > 0);
    }

    private static function previewInvoiceAction(): Action
    {
        return Action::make('preview_invoice')
            ->label(__('app.actions.view_pdf'))
            ->icon('heroicon-o-document-text')
            ->url(fn (Invoice $record): string => route('invoices.preview', $record))
            ->openUrlInNewTab()
            ->visible(fn (Invoice $record): bool => auth()->user()?->can('view', $record) ?? false);
    }

    private static function downloadInvoiceAction(): Action
    {
        return Action::make('download_invoice')
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
            ->visible(fn (Invoice $record): bool => auth()->user()?->can('view', $record) ?? false);
    }

    private static function emailUnavailableReason(
        Invoice $invoice,
        InvoiceEmailService $emailService,
        CommunicationPreferenceService $preferenceService,
        bool $requiresPayment = false,
    ): ?string {
        if (! $emailService->isMailDeliveryConfigured()) {
            return __('app.notifications.mail_not_configured');
        }

        if (! filled($invoice->subscription->member->email ?? null)) {
            return __('app.tooltips.member_email_missing');
        }

        if ($reason = $preferenceService->emailOptOutReason($invoice)) {
            return $reason;
        }

        if ($requiresPayment && (float) ($invoice->paid_amount ?? 0) <= 0) {
            return __('app.tooltips.no_payments_recorded');
        }

        if (! filled($invoice->number) || (float) ($invoice->total_amount ?? 0) <= 0) {
            return __('app.tooltips.invoice_document_missing');
        }

        return null;
    }
}
