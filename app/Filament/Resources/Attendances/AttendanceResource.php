<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Attendances\Tables\AttendanceTable;
use App\Models\Attendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends resource<Attendance> */
class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static ?string $recordTitleAttribute = 'checked_in_at';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getModelLabel(): string
    {
        return __('app.resources.attendance.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.attendance.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['member.name', 'member.code', 'member.email', 'member.contact'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Attendance $record */
        return $record->member->name;
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Attendance $record */
        return [
            __('app.fields.member_code') => $record->member->code,
            __('app.fields.checked_in_at') => $record->checked_in_at->format('d M Y, h:i A'),
        ];
    }

    public static function table(Table $table): Table
    {
        return AttendanceTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendances::route('/'),
        ];
    }

    /** @return Builder<Attendance> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'member',
            'checkedInBy',
        ]);
    }
}
