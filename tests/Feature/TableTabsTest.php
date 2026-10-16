<?php

use App\Enums\Status;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Models\Enquiry;
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

it('renders status tabs in the table toolbar and keeps their filters native', function (): void {
    $lead = Enquiry::factory()->create(['status' => Status::Lead]);
    $lost = Enquiry::factory()->create(['status' => Status::Lost]);

    Livewire::test(ListEnquiries::class)
        ->assertSeeHtml('data-table-tabs')
        ->assertCanSeeTableRecords([$lead, $lost])
        ->call('selectTableTab', Status::Lost->value)
        ->assertSet('activeTab', Status::Lost->value)
        ->assertCanSeeTableRecords([$lost])
        ->assertCanNotSeeTableRecords([$lead]);
});

it('ignores unknown table tabs', function (): void {
    $lead = Enquiry::factory()->create(['status' => Status::Lead]);
    $lost = Enquiry::factory()->create(['status' => Status::Lost]);

    Livewire::test(ListEnquiries::class)
        ->call('selectTableTab', 'unknown')
        ->assertSet('activeTab', 'all')
        ->assertCanSeeTableRecords([$lead, $lost]);
});
