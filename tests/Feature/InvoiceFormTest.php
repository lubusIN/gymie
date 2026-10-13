<?php

use App\Filament\Resources\Invoices\Pages\EditInvoice;
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
        'charges' => ['taxes' => 10, 'discounts' => [10]],
        'subscriptions' => ['expiring_days' => 7],
        'payments' => ['provider' => 'stripe'],
        'notifications' => ['email' => []],
    ]);

    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

it('keeps reactive invoice totals consistent across subscription and discount changes', function (): void {
    $member = Member::factory()->create();
    $initialPlan = Plan::factory()->create(['amount' => 500]);
    $updatedPlan = Plan::factory()->create(['amount' => 1000]);
    $initialSubscription = Subscription::factory()->for($member)->for($initialPlan)->create();
    $updatedSubscription = Subscription::factory()->for($member)->for($updatedPlan)->create();
    $invoice = Invoice::factory()->for($initialSubscription)->create([
        'subscription_fee' => 500,
        'discount_amount' => 0,
        'paid_amount' => 100,
    ]);

    $component = Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
        ->set('data.subscription_id', $updatedSubscription->getKey());

    expect($component->get('data.subscription_fee'))->toEqual(1000)
        ->and($component->get('data.tax'))->toEqual(100)
        ->and($component->get('data.total_amount'))->toEqual(1100)
        ->and($component->get('data.paid_amount'))->toEqual(100)
        ->and($component->get('data.due_amount'))->toEqual(1000);

    $component->set('data.discount', 10);

    expect($component->get('data.discount_amount'))->toEqual(100)
        ->and($component->get('data.total_amount'))->toEqual(1000)
        ->and($component->get('data.due_amount'))->toEqual(900);

    $component->set('data.discount_amount', 200);

    expect($component->get('data.discount_amount'))->toEqual(200)
        ->and($component->get('data.total_amount'))->toEqual(900)
        ->and($component->get('data.due_amount'))->toEqual(800);
});
