<?php

use App\Filament\Resources\Invoices\RelationManagers\InvoiceTransactionsRelationManager;
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
