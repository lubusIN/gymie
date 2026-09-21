<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\Status;
use App\Helpers\Helpers;
use App\Support\AppConfig;
use App\Support\Filament\ExpenseCategorySelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ExpenseForm
{
    /**
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            Status::Pending->value => Status::Pending->getLabel(),
            Status::Paid->value => Status::Paid->getLabel(),
            Status::Overdue->value => Status::Overdue->getLabel(),
            Status::Cancelled->value => Status::Cancelled->getLabel(),
        ];
    }

    /**
     * Configure the expense form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(6)
                    ->schema([
                        Group::make()
                            ->columns(6)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.fields.expense_name'))
                                    ->placeholder(__('app.placeholders.expense_name_example'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                ExpenseCategorySelect::make('category')
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('amount')
                                    ->label(__('app.fields.amount'))
                                    ->prefix(Helpers::getCurrencySymbol())
                                    ->mask(RawJs::make('$money($input)'))
                                    ->stripCharacters([','])
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->columnSpan(2),
                                DatePicker::make('date')
                                    ->label(__('app.fields.date'))
                                    ->default(fn (): string => now()->timezone(AppConfig::timezone())->toDateString())
                                    ->required()
                                    ->columnSpan(2),
                                DatePicker::make('due_date')
                                    ->label(__('app.fields.due_date'))
                                    ->columnSpan(2),
                                Textarea::make('notes')
                                    ->label(__('app.fields.note'))
                                    ->placeholder(__('app.placeholders.optional_note'))
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Select::make('status')
                                    ->label(__('app.fields.status'))
                                    ->options(static::getStatusOptions())
                                    ->default(Status::Pending->value)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                        if ($state === Status::Paid->value) {
                                            if (blank($get('paid_at'))) {
                                                $set('paid_at', now()->timezone(AppConfig::timezone())->format('Y-m-d H:i:s'));
                                            }

                                            return;
                                        }

                                        $set('paid_at', null);
                                    })
                                    ->required()
                                    ->columnSpan(2),
                                DateTimePicker::make('paid_at')
                                    ->label(__('app.fields.paid_at'))
                                    ->seconds(false)
                                    ->timezone(AppConfig::timezone())
                                    ->visible(fn (Get $get): bool => $get('status') === Status::Paid->value)
                                    ->required(fn (Get $get): bool => $get('status') === Status::Paid->value)
                                    ->columnSpan(2),
                                TextInput::make('vendor')
                                    ->label(__('app.fields.vendor'))
                                    ->placeholder(__('app.placeholders.vendor_name'))
                                    ->maxLength(255)
                                    ->columnSpan(2),
                            ]),
                    ]),
            ]);
    }
}
