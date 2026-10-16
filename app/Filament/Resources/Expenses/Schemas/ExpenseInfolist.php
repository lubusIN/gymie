<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpensePaymentMethod;
use App\Helpers\Helpers;
use App\Models\Expense;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class ExpenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Fieldset::make(__('app.titles.expense_details'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('app.fields.expense')),
                        TextEntry::make('category')
                            ->label(__('app.fields.category'))
                            ->formatStateUsing(fn (?string $state): string => Helpers::getExpenseCategoryLabel($state) ?? __('app.placeholders.na')),
                        TextEntry::make('vendor')
                            ->label(__('app.fields.vendor'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('amount')
                            ->label(__('app.fields.amount'))
                            ->money(Helpers::getCurrencyCode()),
                        TextEntry::make('date')
                            ->label(__('app.fields.date'))
                            ->date(),
                        TextEntry::make('due_date')
                            ->label(__('app.fields.due_date'))
                            ->date()
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('paid_at')
                            ->label(__('app.fields.paid_at'))
                            ->dateTime('d M Y, h:i A')
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('payment_method')
                            ->label(__('app.fields.payment_method'))
                            ->formatStateUsing(fn (?string $state): string => ExpensePaymentMethod::tryFrom((string) $state)?->getLabel() ?? __('app.placeholders.na'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('payment_reference')
                            ->label(__('app.fields.payment_reference'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('notes')
                            ->label(__('app.fields.note'))
                            ->placeholder(__('app.placeholders.na'))
                            ->columnSpanFull(),
                        TextEntry::make('receipt_path')
                            ->label(__('app.fields.receipt'))
                            ->formatStateUsing(fn (): string => __('app.common.available'))
                            ->icon('heroicon-o-paper-clip')
                            ->url(fn (Expense $record): string => route('expenses.receipts.preview', $record))
                            ->openUrlInNewTab()
                            ->suffixActions([
                                Action::make('preview_receipt')
                                    ->label(__('app.actions.view', ['resource' => __('app.fields.receipt')]))
                                    ->icon('heroicon-o-eye')
                                    ->url(fn (Expense $record): string => route('expenses.receipts.preview', $record))
                                    ->openUrlInNewTab(),
                                Action::make('download_receipt')
                                    ->label(__('app.actions.download'))
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->url(fn (Expense $record): string => route('expenses.receipts.download', $record)),
                            ])
                            ->visible(fn (Expense $record): bool => $record->hasReceipt())
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
