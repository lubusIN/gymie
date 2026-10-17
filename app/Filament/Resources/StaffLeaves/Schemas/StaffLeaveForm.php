<?php

namespace App\Filament\Resources\StaffLeaves\Schemas;

use App\Enums\StaffLeaveType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StaffLeaveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.staff_leave.request_details'))
                    ->schema([
                        Select::make('staff_id')
                            ->label(__('app.staff_leave.staff_member'))
                            ->relationship(
                                'staff',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                            )
                            ->default(fn (): ?int => auth()->id())
                            ->disabled(fn (): bool => ! self::canManageAllLeaves())
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('type')
                            ->label(__('app.fields.leave_type'))
                            ->options(StaffLeaveType::class)
                            ->required(),
                        DatePicker::make('start_date')
                            ->label(__('app.fields.date_from'))
                            ->native(false)
                            ->required(),
                        DatePicker::make('end_date')
                            ->label(__('app.fields.date_to'))
                            ->native(false)
                            ->afterOrEqual('start_date')
                            ->required(),
                        Textarea::make('reason')
                            ->label(__('app.fields.reason'))
                            ->rows(4)
                            ->maxLength(2000)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    private static function canManageAllLeaves(): bool
    {
        return auth()->user()?->can('ViewAny:StaffLeave') ?? false;
    }
}
