<?php

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('hides invoice mutation actions from staff without update permission', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo([
        Permission::findOrCreate('ViewAny:Invoice', 'web'),
        Permission::findOrCreate('View:Invoice', 'web'),
    ]);

    $unpaidInvoice = Invoice::factory()->create([
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 0,
    ])->refresh();
    $paidInvoice = Invoice::factory()->create([
        'subscription_fee' => 100,
        'discount_amount' => 0,
        'paid_amount' => 50,
    ])->refresh();

    $this->actingAs($viewer);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListInvoices::class)
        ->assertTableActionHidden('add_payment', $unpaidInvoice)
        ->assertTableActionHidden('cancel_invoice', $unpaidInvoice)
        ->assertTableActionHidden('send_payment_reminder', $unpaidInvoice)
        ->assertTableActionHidden('email_invoice', $unpaidInvoice)
        ->assertTableActionHidden('refund', $paidInvoice)
        ->assertTableActionHidden('email_receipt', $paidInvoice);
});
