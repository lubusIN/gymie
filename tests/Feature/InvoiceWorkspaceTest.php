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
use Illuminate\Support\Carbon;
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
    $this->travelBack();
    Helpers::setTestSettingsOverride(null);
});

it('organizes invoice details and payment history into a focused workspace', function (): void {
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));

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
        ->assertActionHidden('email_invoice')
        ->assertActionVisible('email_invoice_menu')
        ->assertActionVisible('preview_invoice')
        ->assertActionHidden('download_invoice')
        ->assertActionVisible('download_invoice_menu')
        ->assertSee('Invoice #INV-100')
        ->assertDontSee('Invoice No.')
        ->assertSee('Taylor Member')
        ->assertSee('Strength 30')
        ->assertSee('Membership Period')
        ->assertSee('01 Oct 2026 – 30 Oct 2026')
        ->assertSee('14 days overdue')
        ->assertSee('Payment Summary')
        ->assertSee(Helpers::formatCurrency((float) $invoice->total_amount))
        ->assertSee(Helpers::formatCurrency((float) $invoice->due_amount));
});

it('shows document actions before the menu for paid invoices', function (): void {
    $member = Member::factory()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($member)->for($plan)->create();
    $invoice = Invoice::query()->create([
        'number' => 'INV-PAID',
        'subscription_id' => $subscription->getKey(),
        'date' => now()->toDateString(),
        'due_date' => now()->addWeek()->toDateString(),
        'payment_method' => 'cash',
        'status' => 'issued',
        'subscription_fee' => 5000,
        'paid_amount' => 5000,
    ])->refresh();

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertActionHidden('add_payment')
        ->assertActionHidden('send_payment_reminder')
        ->assertActionVisible('download_invoice')
        ->assertActionVisible('email_invoice')
        ->assertActionHidden('download_invoice_menu')
        ->assertActionHidden('email_invoice_menu');
});
