<?php

namespace App\Filament\Resources\StaffActivities\Tables;

use App\Models\Attendance;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Service;
use App\Models\StaffActivity;
use App\Models\Subscription;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaffActivitiesTable
{
    public static function configure(Table $table, bool $showStaff = true): Table
    {
        return $table
            ->columns([
                ...($showStaff ? [
                    TextColumn::make('causer.name')
                        ->label(__('app.staff_activity.staff'))
                        ->searchable(),
                ] : []),
                TextColumn::make('event')
                    ->label(__('app.staff_activity.action'))
                    ->formatStateUsing(fn (StaffActivity $record): string => $record->actionLabel())
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created', 'restored' => 'success',
                        'deleted', 'force_deleted' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('module')
                    ->label(__('app.staff_activity.module'))
                    ->state(fn (StaffActivity $record): string => $record->moduleLabel()),
                TextColumn::make('subject')
                    ->label(__('app.staff_activity.record'))
                    ->state(fn (StaffActivity $record): string => $record->subjectLabel())
                    ->url(fn (StaffActivity $record): ?string => $record->subjectUrl()),
                TextColumn::make('changes')
                    ->label(__('app.staff_activity.changed_fields'))
                    ->state(fn (StaffActivity $record): string => $record->changedFieldsLabel())
                    ->placeholder(__('app.placeholders.na'))
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('app.staff_activity.occurred_at'))
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                ...($showStaff ? [
                    SelectFilter::make('causer_id')
                        ->label(__('app.staff_activity.staff'))
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                ] : []),
                SelectFilter::make('event')
                    ->label(__('app.staff_activity.action'))
                    ->options([
                        'created' => __('app.staff_activity.events.created'),
                        'updated' => __('app.staff_activity.events.updated'),
                        'deleted' => __('app.staff_activity.events.deleted'),
                        'restored' => __('app.staff_activity.events.restored'),
                        'force_deleted' => __('app.staff_activity.events.force_deleted'),
                    ]),
                SelectFilter::make('subject_type')
                    ->label(__('app.staff_activity.module'))
                    ->options(self::moduleOptions()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('date_from')->label(__('app.fields.date_from')),
                        DatePicker::make('date_to')->label(__('app.fields.date_to')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['date_from'], fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['date_to'], fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->hiddenLabel(),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading(__('app.staff_activity.empty'))
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<class-string, string> */
    private static function moduleOptions(): array
    {
        return collect([
            Attendance::class,
            Enquiry::class,
            Expense::class,
            Invoice::class,
            InvoiceTransaction::class,
            Member::class,
            Plan::class,
            Service::class,
            Subscription::class,
            User::class,
        ])->mapWithKeys(fn (string $model): array => [$model => str($model)->classBasename()->headline()->toString()])->all();
    }
}
