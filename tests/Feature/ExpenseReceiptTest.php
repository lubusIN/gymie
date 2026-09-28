<?php

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Filament::setCurrentPanel('admin');
});

it('stores an uploaded receipt on the private disk', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Expense::factory()->create();

    Livewire::test(ListExpenses::class)
        ->callAction('create', [
            'name' => 'Equipment repair',
            'amount' => 2500,
            'date' => '2026-09-28',
            'category' => 'maintenance',
            'status' => 'pending',
            'receipt_path' => UploadedFile::fake()->create('repair-receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertHasNoActionErrors();

    $expense = Expense::query()->where('name', 'Equipment repair')->firstOrFail();

    expect($expense->receipt_path)
        ->toBeString()
        ->toStartWith('expenses/receipts/');

    Storage::disk('local')->assertExists($expense->receipt_path);
});

it('rejects unsupported receipt file types', function (): void {
    Role::findOrCreate('super_admin', 'web');
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    Expense::factory()->create();

    Livewire::test(ListExpenses::class)
        ->callAction('create', [
            'name' => 'Unsafe receipt',
            'amount' => 100,
            'date' => '2026-09-28',
            'category' => 'other',
            'status' => 'pending',
            'receipt_path' => UploadedFile::fake()->create('receipt.php', 10, 'application/x-php'),
        ])
        ->assertHasActionErrors(['receipt_path']);

    expect(Expense::query()->where('name', 'Unsafe receipt')->exists())->toBeFalse();
});

it('previews and downloads a receipt for an authorized user', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('View:Expense', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('View:Expense');
    $expense = Expense::factory()->create([
        'receipt_path' => 'expenses/receipts/receipt.pdf',
    ]);
    Storage::disk('local')->put($expense->receipt_path, 'receipt contents');

    $preview = $this->actingAs($user)
        ->get(route('expenses.receipts.preview', $expense))
        ->assertOk();

    expect($preview->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain("expense-{$expense->getKey()}-receipt.pdf");

    $this->actingAs($user)
        ->get(route('expenses.receipts.download', $expense))
        ->assertDownload("expense-{$expense->getKey()}-receipt.pdf");
});

it('does not expose receipts without expense permission', function (): void {
    $expense = Expense::factory()->create([
        'receipt_path' => 'expenses/receipts/receipt.pdf',
    ]);
    Storage::disk('local')->put($expense->receipt_path, 'receipt contents');

    $this->actingAs(User::factory()->create())
        ->get(route('expenses.receipts.preview', $expense))
        ->assertForbidden();
});

it('returns not found when the receipt is missing or outside its private directory', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('View:Expense', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('View:Expense');
    $expense = Expense::factory()->create([
        'receipt_path' => 'other-private-file.pdf',
    ]);
    Storage::disk('local')->put($expense->receipt_path, 'private contents');

    $this->actingAs($user)
        ->get(route('expenses.receipts.preview', $expense))
        ->assertNotFound();
});

it('removes replaced and force-deleted receipt files while preserving soft-deleted receipts', function (): void {
    Storage::disk('local')->put('expenses/receipts/old.pdf', 'old');
    Storage::disk('local')->put('expenses/receipts/new.pdf', 'new');

    $expense = Expense::factory()->create([
        'receipt_path' => 'expenses/receipts/old.pdf',
    ]);

    $expense->update(['receipt_path' => 'expenses/receipts/new.pdf']);

    Storage::disk('local')->assertMissing('expenses/receipts/old.pdf');
    Storage::disk('local')->assertExists('expenses/receipts/new.pdf');

    $expense->delete();
    Storage::disk('local')->assertExists('expenses/receipts/new.pdf');

    $expense->forceDelete();
    Storage::disk('local')->assertMissing('expenses/receipts/new.pdf');
});
