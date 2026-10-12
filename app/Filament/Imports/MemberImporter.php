<?php

namespace App\Filament\Imports;

use App\Models\Member;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

class MemberImporter extends Importer
{
    protected static ?string $model = Member::class;

    protected static bool $shouldPreventFormulaInjection = true;

    /** @return array<ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label(__('app.fields.member_code'))
                ->example('GY-MEM-0001')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('name')
                ->label(__('app.fields.name'))
                ->requiredMapping()
                ->example('Alex Morgan')
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')
                ->label(__('app.fields.email'))
                ->requiredMapping()
                ->example('alex@example.com')
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('contact')
                ->label(__('app.fields.contact'))
                ->requiredMapping()
                ->example('+91 98765 43210')
                ->rules(['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-\(\)]+$/']),
            ImportColumn::make('emergency_contact')
                ->label(__('app.fields.emergency_contact'))
                ->example('+91 98765 43211')
                ->rules(['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-\(\)]+$/'])
                ->ignoreBlankState(),
            ImportColumn::make('gender')
                ->label(__('app.fields.gender'))
                ->example('male')
                ->rules(['nullable', 'in:male,female,other'])
                ->ignoreBlankState(),
            ImportColumn::make('dob')
                ->label(__('app.fields.dob'))
                ->example('1995-08-24')
                ->rules(['nullable', 'date'])
                ->ignoreBlankState(),
            ImportColumn::make('health_issue')
                ->label(__('app.fields.health_issues'))
                ->rules(['nullable', 'string', 'max:500'])
                ->ignoreBlankState(),
            ImportColumn::make('address')
                ->label(__('app.fields.address'))
                ->example('21 Market Road')
                ->rules(['nullable', 'string'])
                ->ignoreBlankState(),
            ImportColumn::make('country')
                ->label(__('app.fields.country'))
                ->example('IN')
                ->rules(['nullable', 'string', 'size:2'])
                ->castStateUsing(fn (mixed $state): ?string => filled($state) ? strtoupper(trim((string) $state)) : null)
                ->ignoreBlankState(),
            ImportColumn::make('state')
                ->label(__('app.fields.state'))
                ->example('Maharashtra')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('city')
                ->label(__('app.fields.city'))
                ->example('Mumbai')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('pincode')
                ->label(__('app.fields.pincode'))
                ->example('400001')
                ->rules(['nullable', 'string', 'max:20'])
                ->ignoreBlankState(),
            ImportColumn::make('source')
                ->label(__('app.fields.source'))
                ->example('promotions')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('goal')
                ->label(__('app.fields.goal'))
                ->example('fitness')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('status')
                ->label(__('app.fields.status'))
                ->example('active')
                ->rules(['nullable', 'in:active,inactive'])
                ->ignoreBlankState(),
        ];
    }

    /** Allow an explicit member code to update a profile when requested. */
    public function resolveRecord(): Member
    {
        Gate::forUser($this->getImport()->user)->authorize('Import:Member');

        $code = trim((string) ($this->data['code'] ?? ''));

        if ($code === '') {
            return new Member;
        }

        $member = Member::query()->where('code', $code)->first();

        if (! $member) {
            return new Member;
        }

        if ($this->options['updateExisting'] ?? false) {
            return $member;
        }

        throw new RowImportFailedException(__('app.imports.member_code_exists', ['code' => $code]));
    }

    /** @return array<Checkbox> */
    public static function getOptionsFormComponents(): array
    {
        return [
            Checkbox::make('updateExisting')
                ->label(__('app.imports.update_existing_members'))
                ->helperText(__('app.imports.update_existing_members_help')),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('app.imports.completed', ['count' => Number::format($import->successful_rows)]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__('app.imports.failed', ['count' => Number::format($failedRowsCount)]);
        }

        return $body;
    }
}
