<?php

use App\Filament\Exports\MemberExporter;
use App\Filament\Imports\MemberImporter;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('Import:Member', 'web');
    Permission::findOrCreate('Export:Member', 'web');
});

it('imports member profiles without treating shared contact details as duplicates', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('Import:Member');
    $importer = memberImporter($user);

    $importer(memberRow('GY-MEM-1001', 'First Member'));
    $importer(memberRow('GY-MEM-1002', 'Second Member'));

    expect(Member::query()->where('email', 'shared@example.com')->count())->toBe(2)
        ->and(Member::query()->where('contact', '+91 98765 43210')->count())->toBe(2);
});

it('updates only an explicit matching member code when enabled', function (): void {
    $member = Member::factory()->create([
        'code' => 'GY-MEM-2001',
        'name' => 'Old Name',
    ]);
    $user = User::factory()->create();
    $user->givePermissionTo('Import:Member');

    memberImporter($user, updateExisting: true)(memberRow($member->code, 'Updated Name'));

    expect($member->refresh()->name)->toBe('Updated Name')
        ->and(Member::query()->where('code', $member->code)->count())->toBe(1);
});

it('rejects an existing member code unless updates are explicitly enabled', function (): void {
    $member = Member::factory()->create(['code' => 'GY-MEM-3001']);
    $user = User::factory()->create();
    $user->givePermissionTo('Import:Member');

    expect(fn () => memberImporter($user)(memberRow($member->code, 'Replacement Name')))
        ->toThrow(RowImportFailedException::class);

    expect($member->refresh()->name)->not->toBe('Replacement Name');
});

it('neutralizes spreadsheet formulas in exported member text', function (): void {
    $user = User::factory()->create();
    $member = Member::factory()->create(['name' => '=HYPERLINK("https://example.com")']);
    $export = Export::query()->create([
        'file_disk' => 'local',
        'exporter' => MemberExporter::class,
        'total_rows' => 1,
        'user_id' => $user->getKey(),
    ]);
    $exporter = $export->getExporter(['name' => 'Name'], []);

    expect($exporter($member))->toBe(['\'=HYPERLINK("https://example.com")']);
});

it('offers a custom file name field for member exports', function (): void {
    app()->setLocale('en');

    $fileNameField = MemberExporter::getOptionsFormComponents()[0];

    expect($fileNameField)->toBeInstanceOf(TextInput::class);
    expect($fileNameField->getName())->toBe('fileName');
    expect($fileNameField->getLabel())->toBe('File name');
}
);

it('uses a safe custom file name for member exports', function (): void {
    $user = User::factory()->create();
    $export = Export::query()->create([
        'file_disk' => 'local',
        'exporter' => MemberExporter::class,
        'total_rows' => 1,
        'user_id' => $user->getKey(),
    ]);
    $exporter = $export->getExporter(['name' => 'Name'], [
        'fileName' => '../../October Members.xlsx',
    ]);

    expect($exporter->getFileName($export))->toBe('October-Members');
});

function memberImporter(User $user, bool $updateExisting = false): MemberImporter
{
    $import = Import::query()->create([
        'file_name' => 'members.csv',
        'file_path' => 'imports/members.csv',
        'importer' => MemberImporter::class,
        'total_rows' => 1,
        'user_id' => $user->getKey(),
    ]);

    return $import->getImporter([
        'code' => 'code',
        'name' => 'name',
        'email' => 'email',
        'contact' => 'contact',
        'status' => 'status',
    ], [
        'updateExisting' => $updateExisting,
    ]);
}

/** @return array<string, string> */
function memberRow(string $code, string $name): array
{
    return [
        'code' => $code,
        'name' => $name,
        'email' => 'shared@example.com',
        'contact' => '+91 98765 43210',
        'status' => 'active',
    ];
}
