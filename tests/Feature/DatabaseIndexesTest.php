<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('provides indexes for recurring billing and membership queries', function (): void {
    $subscriptions = collect(Schema::getIndexes('subscriptions'))->pluck('columns');
    $invoices = collect(Schema::getIndexes('invoices'))->pluck('columns');
    $transactions = collect(Schema::getIndexes('invoice_transactions'))->pluck('columns');
    $expenses = collect(Schema::getIndexes('expenses'))->pluck('columns');
    $attendances = collect(Schema::getIndexes('attendances'))->pluck('columns');
    $staffAttendances = collect(Schema::getIndexes('staff_attendances'))->pluck('columns');
    $communicationDeliveries = collect(Schema::getIndexes('communication_deliveries'))->pluck('columns');

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

    expect($expenses)
        ->toContain(['status', 'paid_at'])
        ->toContain(['status', 'due_date']);

    expect($attendances)
        ->toContain(['member_id', 'visited_on'])
        ->toContain(['member_id', 'checked_in_at'])
        ->toContain(['visited_on', 'checked_in_at']);

    expect($staffAttendances)
        ->toContain(['staff_id', 'visited_on'])
        ->toContain(['staff_id', 'checked_in_at'])
        ->toContain(['visited_on', 'checked_in_at']);

    expect($communicationDeliveries)
        ->toContain(['status', 'queued_at'])
        ->toContain(['communication_template_id', 'queued_at'])
        ->toContain(['recipient', 'queued_at']);
});
