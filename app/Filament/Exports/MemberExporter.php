<?php

namespace App\Filament\Exports;

use App\Models\Member;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class MemberExporter extends Exporter
{
    protected static ?string $model = Member::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            self::textColumn('code', __('app.fields.member_code')),
            self::textColumn('name', __('app.fields.name')),
            self::textColumn('email', __('app.fields.email')),
            self::textColumn('contact', __('app.fields.contact')),
            self::textColumn('emergency_contact', __('app.fields.emergency_contact')),
            self::textColumn('gender', __('app.fields.gender')),
            ExportColumn::make('dob')->label(__('app.fields.dob')),
            self::textColumn('health_issue', __('app.fields.health_issues'))
                ->enabledByDefault(false),
            self::textColumn('address', __('app.fields.address')),
            self::textColumn('country', __('app.fields.country')),
            self::textColumn('state', __('app.fields.state')),
            self::textColumn('city', __('app.fields.city')),
            self::textColumn('pincode', __('app.fields.pincode')),
            self::textColumn('source', __('app.fields.source')),
            self::textColumn('goal', __('app.fields.goal')),
            self::textColumn('status', __('app.fields.status')),
            ExportColumn::make('created_at')->label(__('app.fields.created_at')),
        ];
    }

    /** @return array<TextInput> */
    public static function getOptionsFormComponents(): array
    {
        return [
            TextInput::make('fileName')
                ->label(__('app.exports.file_name'))
                ->helperText(__('app.exports.file_name_help'))
                ->default(fn (): string => 'members-'.now()->format('Y-m-d'))
                ->required()
                ->maxLength(100),
        ];
    }

    public function getFileName(Export $export): string
    {
        $fileName = Str::of((string) ($this->options['fileName'] ?? ''))
            ->trim()
            ->replaceMatches('/\.(csv|xlsx)$/i', '')
            ->replaceMatches('/[^\pL\pN._-]+/u', '-')
            ->trim('.-_')
            ->limit(100, '')
            ->toString();

        return filled($fileName) ? $fileName : "members-{$export->getKey()}";
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('app.exports.completed', [
            'count' => Str::of('row')->counted($export->successful_rows),
        ]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__('app.exports.failed', [
                'count' => Str::of('row')->counted($failedRowsCount),
            ]);
        }

        return $body;
    }

    /** Create a spreadsheet-safe column for user-provided text. */
    private static function textColumn(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->preventFormulaInjection();
    }
}
