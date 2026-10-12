<?php

use App\Enums\CommunicationDeliveryStatus;
use App\Filament\Widgets\Analytics\FailedCommunicationDeliveriesTableWidget;
use App\Models\CommunicationDelivery;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Filament::setCurrentPanel('admin');
});

it('shows only failed communication deliveries that require attention', function (): void {
    $failedDelivery = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
        'failure_reason' => 'Provider rejected the message.',
    ]);
    $sentDelivery = CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Sent,
        'sent_at' => now(),
    ]);

    expect(FailedCommunicationDeliveriesTableWidget::canView())->toBeTrue();

    Livewire::test(FailedCommunicationDeliveriesTableWidget::class)
        ->assertCanSeeTableRecords([$failedDelivery])
        ->assertCanNotSeeTableRecords([$sentDelivery])
        ->assertSee('Provider rejected the message.');
});

it('stays hidden when there are no failed deliveries', function (): void {
    CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Sent,
        'sent_at' => now(),
    ]);

    expect(FailedCommunicationDeliveriesTableWidget::canView())->toBeFalse();
});

it('stays hidden from staff without delivery history permission', function (): void {
    CommunicationDelivery::factory()->create([
        'status' => CommunicationDeliveryStatus::Failed,
        'failed_at' => now(),
    ]);
    $this->actingAs(User::factory()->create());

    expect(FailedCommunicationDeliveriesTableWidget::canView())->toBeFalse();
});
