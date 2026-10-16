<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Expenses\Schemas\ExpenseInfolist;
use App\Filament\Resources\Expenses\Tables\ExpenseTable;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Support\Filament\StatusBadge;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('app.resources.expenses.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.expenses.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'category',
            'vendor',
            'notes',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Expense $record */
        return [
            __('app.fields.date') => $record->date->toDateString(),
            __('app.fields.status') => StatusBadge::render($record->status),
            __('app.fields.amount') => Helpers::formatCurrency((float) $record->amount),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExpenseInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
        ];
    }
}
