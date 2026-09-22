<?php

use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Member;
use App\Services\Invoices\InvoiceTransactionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Helpers::setTestSettingsOverride([
        'general' => ['currency' => 'INR'],
        'charges' => ['taxes' => 0, 'discounts' => []],
        'subscriptions' => ['expiring_days' => 7],
        'payments' => ['provider' => 'stripe'],
        'notifications' => ['email' => []],
    ]);
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('records and removes transactions while keeping invoice totals synchronized', function (): void {
    $invoice = Invoice::factory()->create([
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);
    $service = app(InvoiceTransactionService::class);

    $transaction = $service->record($invoice, [
        'type' => 'payment',
        'amount' => 40.25,
        'occurred_at' => now(),
    ]);

    expect($transaction->amount)->toBe('40.25')
        ->and($invoice->fresh()->paid_amount)->toBe('40.25')
        ->and($invoice->fresh()->due_amount)->toBe('59.75');

    $service->delete($invoice, $transaction);

    expect($invoice->fresh()->paid_amount)->toBe('0.00')
        ->and($invoice->fresh()->due_amount)->toBe('100.00');
});

it('rejects a transaction that exceeds the available invoice balance', function (): void {
    $invoice = Invoice::factory()->create([
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ]);

    expect(fn () => app(InvoiceTransactionService::class)->record($invoice, [
        'type' => 'payment',
        'amount' => 100.01,
        'occurred_at' => now(),
    ]))->toThrow(ValidationException::class);

    expect($invoice->transactions()->count())->toBe(0);
});

it('enforces unique member codes at the database boundary', function (): void {
    Member::factory()->create(['code' => 'GY-100']);

    expect(fn () => Member::factory()->create(['code' => 'GY-100']))
        ->toThrow(QueryException::class);
});
