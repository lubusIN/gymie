<?php

namespace App\Filament\Resources\StaffActivities\Schemas;

use App\Models\StaffActivity;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.staff_activity.details'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('causer.name')->label(__('app.staff_activity.staff')),
                        TextEntry::make('event')
                            ->label(__('app.staff_activity.action'))
                            ->formatStateUsing(fn (StaffActivity $record): string => $record->actionLabel())
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label(__('app.staff_activity.occurred_at'))
                            ->dateTime('d M Y, h:i A'),
                        TextEntry::make('module')
                            ->label(__('app.staff_activity.module'))
                            ->state(fn (StaffActivity $record): string => $record->moduleLabel()),
                        TextEntry::make('subject')
                            ->label(__('app.staff_activity.record'))
                            ->state(fn (StaffActivity $record): string => $record->subjectLabel())
                            ->url(fn (StaffActivity $record): ?string => $record->subjectUrl())
                            ->columnSpan(2),
                    ]),
                Section::make(__('app.staff_activity.changes'))
                    ->columns(2)
                    ->schema([
                        KeyValueEntry::make('old_values')
                            ->label(__('app.staff_activity.before'))
                            ->state(fn (StaffActivity $record): array => $record->oldValues()->all())
                            ->keyLabel(__('app.staff_activity.field'))
                            ->valueLabel(__('app.staff_activity.value')),
                        KeyValueEntry::make('new_values')
                            ->label(__('app.staff_activity.after'))
                            ->state(fn (StaffActivity $record): array => $record->newValues()->all())
                            ->keyLabel(__('app.staff_activity.field'))
                            ->valueLabel(__('app.staff_activity.value')),
                    ]),
            ]);
    }
}
