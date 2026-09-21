<?php

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->user);
    Filament::setCurrentPanel('admin');
});

it('shows the refund amount as read only', function (): void {
    $invoice = Invoice::factory()->create([
        'subscription_fee' => 100,
        'discount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 60,
    ]);

    Livewire::test(ListInvoices::class)
        ->mountTableAction('refund', $invoice)
        ->assertSchemaComponentExists(
            'refund_amount',
            checkComponentUsing: fn ($component): bool => $component instanceof TextEntry,
        )
        ->assertSchemaComponentDoesNotExist('amount')
        ->assertSchemaComponentExists('occurred_at')
        ->assertSchemaComponentExists('note');
});

it('refunds the full paid balance', function (): void {
    $invoice = Invoice::factory()->create([
        'subscription_fee' => 100,
        'discount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 60,
    ]);
    $refundableAmount = (float) $invoice->paid_amount;

    Livewire::test(ListInvoices::class)
        ->callTableAction('refund', $invoice, [
            'amount' => 1,
            'occurred_at' => '2026-09-21 10:30:00',
            'note' => 'Customer requested a refund.',
        ])
        ->assertNotified();

    $refund = $invoice->transactions()
        ->where('type', 'refund')
        ->sole();

    expect((float) $refund->amount)->toBe($refundableAmount)
        ->and($refund->occurred_at?->format('Y-m-d H:i:s'))->toBe('2026-09-21 10:30:00')
        ->and($refund->note)->toBe('Customer requested a refund.')
        ->and($refund->created_by)->toBe($this->user->getKey())
        ->and($invoice->fresh()->status?->value)->toBe('refund');
});
