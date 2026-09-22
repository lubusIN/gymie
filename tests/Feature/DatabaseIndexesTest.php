<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('provides indexes for recurring billing and membership queries', function (): void {
    $subscriptions = collect(Schema::getIndexes('subscriptions'))->pluck('columns');
    $invoices = collect(Schema::getIndexes('invoices'))->pluck('columns');
    $transactions = collect(Schema::getIndexes('invoice_transactions'))->pluck('columns');

    expect($subscriptions)
        ->toContain(['start_date'])
        ->toContain(['end_date'])
        ->toContain(['status', 'end_date']);

    expect($invoices)
        ->toContain(['date'])
        ->toContain(['status', 'date'])
        ->toContain(['status', 'due_date']);

    expect($transactions)
        ->toContain(['occurred_at', 'type'])
        ->toContain(['invoice_id', 'occurred_at']);
});
