<?php

use App\Filament\Resources\Invoices\RelationManagers\InvoiceTransactionsRelationManager;
use App\Models\InvoiceTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

it('formats payment history dates with a readable twelve-hour time', function (): void {
    config()->set('app.timezone', 'UTC');

    $manager = new InvoiceTransactionsRelationManager;
    $column = $manager
        ->table(Table::make($manager))
        ->getColumn('occurred_at');

    expect($column)->toBeInstanceOf(TextColumn::class)
        ->and($column->formatState('2026-09-26 14:52:19'))
        ->toBe('26 Sep 2026, 02:52 PM');
});

it('uses signed colored amounts instead of a separate transaction type column', function (): void {
    $manager = new InvoiceTransactionsRelationManager;
    $table = $manager->table(Table::make($manager));
    $amountColumn = $table->getColumn('amount');
    $payment = new InvoiceTransaction(['type' => 'payment', 'amount' => 500]);
    $refund = new InvoiceTransaction(['type' => 'refund', 'amount' => 125]);

    expect($table->getColumn('type'))->toBeNull()
        ->and($amountColumn)->toBeInstanceOf(TextColumn::class);

    $amountColumn->record($payment);

    expect($amountColumn->formatState($payment->amount))->toStartWith('+')
        ->and($amountColumn->getColor($payment->amount))->toBe('success');

    $amountColumn->record($refund);

    expect($amountColumn->formatState($refund->amount))->toStartWith('−')
        ->and($amountColumn->getColor($refund->amount))->toBe('danger');
});
