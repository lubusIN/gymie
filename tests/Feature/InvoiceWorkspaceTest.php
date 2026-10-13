<?php

use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Helpers::setTestSettingsOverride([
        'general' => ['currency' => 'INR'],
        'charges' => ['taxes' => 0, 'discounts' => []],
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('organizes invoice details and payment history into a focused workspace', function (): void {
    $member = Member::factory()->create([
        'code' => 'MEM-100',
        'name' => 'Taylor Member',
    ]);
    $plan = Plan::factory()->create([
        'code' => 'STRENGTH-30',
        'name' => 'Strength 30',
    ]);
    $subscription = Subscription::factory()->for($member)->for($plan)->create([
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-30',
        'status' => 'ongoing',
    ]);
    $invoice = Invoice::query()->create([
        'number' => 'INV-100',
        'subscription_id' => $subscription->getKey(),
        'date' => '2026-10-01',
        'due_date' => '2026-10-05',
        'payment_method' => 'cash',
        'status' => 'issued',
        'subscription_fee' => 5000,
        'discount' => 10,
        'discount_amount' => 500,
        'discount_note' => 'October offer',
        'paid_amount' => 1000,
    ])->refresh();

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSchemaComponentExists('invoice_sections.details_tab')
        ->assertSchemaComponentExists('invoice_sections.payment_history_tab')
        ->assertActionVisible('add_payment')
        ->assertActionVisible('send_payment_reminder')
        ->assertActionVisible('email_invoice')
        ->assertActionVisible('preview_invoice')
        ->assertActionVisible('download_invoice')
        ->assertSee('INV-100')
        ->assertSee('Taylor Member')
        ->assertSee('Strength 30')
        ->assertSee(Helpers::formatCurrency((float) $invoice->total_amount))
        ->assertSee(Helpers::formatCurrency((float) $invoice->due_amount));
});
