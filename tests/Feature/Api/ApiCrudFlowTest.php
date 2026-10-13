<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('supports core CRUD flows for integrations', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'Create:Service', 'ViewAny:Service', 'View:Service', 'Update:Service', 'Delete:Service',
        'Create:Plan', 'ViewAny:Plan', 'View:Plan', 'Update:Plan', 'Delete:Plan',
        'Create:Member', 'ViewAny:Member', 'View:Member', 'Update:Member', 'Delete:Member',
        'Create:Subscription', 'ViewAny:Subscription', 'View:Subscription', 'Update:Subscription', 'Delete:Subscription',
        'ViewAny:Invoice', 'View:Invoice', 'Update:Invoice',
        'Create:Expense', 'ViewAny:Expense', 'View:Expense', 'Update:Expense', 'Delete:Expense',
        'Create:Enquiry', 'ViewAny:Enquiry', 'View:Enquiry', 'Update:Enquiry', 'Delete:Enquiry',
        'Create:FollowUp', 'ViewAny:FollowUp', 'View:FollowUp', 'Update:FollowUp', 'Delete:FollowUp',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    Sanctum::actingAs($user);

    $service = $this->postJson('/api/v1/services', [
        'name' => 'Fitness',
        'description' => 'Gym services',
    ])->assertSuccessful()->json('data');

    $plan = $this->postJson('/api/v1/plans', [
        'name' => 'Monthly',
        'code' => 'M-01',
        'service_id' => $service['id'],
        'days' => 30,
        'amount' => 1000,
        'status' => 'active',
    ])->assertSuccessful()->json('data');

    $member = $this->postJson('/api/v1/members', [
        'name' => 'Alex',
        'email' => 'alex@example.com',
        'email_communication_enabled' => false,
        'contact' => '+91 9000000000',
        'gender' => 'male',
        'status' => 'active',
    ])->assertSuccessful()->json('data');

    expect($member['email_communication_enabled'])->toBeFalse();

    $this->patchJson("/api/v1/members/{$member['id']}", [
        'email_communication_enabled' => true,
    ])->assertSuccessful()
        ->assertJsonPath('data.email_communication_enabled', true);

    $subscriptionResponse = $this->postJson('/api/v1/subscriptions', [
        'member_id' => $member['id'],
        'plan_id' => $plan['id'],
        'start_date' => '2026-03-01',
        'invoice' => [
            'date' => '2026-03-01',
            'due_date' => '2026-03-01',
            'payment_method' => 'cash',
            'paid_amount' => 0,
        ],
    ])->assertSuccessful();

    $subscription = $subscriptionResponse->json('data');
    $invoiceId = (int) Invoice::query()
        ->where('subscription_id', (int) $subscription['id'])
        ->value('id');

    expect($invoiceId)->toBeGreaterThan(0);

    $this->patchJson("/api/v1/invoices/{$invoiceId}", [
        'discount' => 101,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('discount');

    $this->postJson('/api/v1/subscriptions', [
        'member_id' => $member['id'],
        'plan_id' => $plan['id'],
        'start_date' => '2026-04-01',
        'invoice' => [
            'discount' => 'invalid',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('invoice.discount');

    $this->postJson("/api/v1/invoices/{$invoiceId}/transactions", [
        'type' => 'payment',
        'amount' => 250,
        'payment_method' => 'cash',
        'note' => 'Partial payment',
    ])->assertSuccessful();

    $pdf = $this->get("/api/v1/invoices/{$invoiceId}/pdf");
    $pdf->assertSuccessful();
    expect((string) $pdf->headers->get('content-type'))->toContain('application/pdf');

    $this->postJson('/api/v1/expenses', [
        'name' => 'Rent',
        'amount' => 100,
        'date' => '2026-03-01',
        'category' => 'rent',
        'status' => 'pending',
    ])->assertSuccessful();

    $enquiry = $this->postJson('/api/v1/enquiries', [
        'name' => 'Lead',
        'email' => 'lead@example.com',
        'contact' => '+91 9000000001',
        'dob' => '2000-01-01',
        'gender' => 'male',
        'follow_up' => [
            'method' => 'call',
            'schedule_date' => '2026-03-02',
        ],
    ])->assertSuccessful()->json('data');

    expect((int) $enquiry['user_id'])->toBe((int) $user->id);

    $this->getJson("/api/v1/enquiries/{$enquiry['id']}/follow-ups")
        ->assertSuccessful();
});
