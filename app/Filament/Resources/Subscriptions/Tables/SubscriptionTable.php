<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\Status;
use App\Filament\Resources\Subscriptions\Schemas\SubscriptionForm;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\AppConfig;
use App\Support\Filament\StatusAction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionTable
{
    /**
     * Configure the subscription table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('member.name')
                    ->label(__('app.fields.member'))
                    ->description(fn ($record): string => $record->member->code),
                TextColumn::make('plan.name')
                    ->label(__('app.fields.plan'))
                    ->description(fn ($record): string => $record->plan->code),
                TextColumn::make('start_date')
                    ->label(__('app.fields.start_date'))
                    ->date(),
                TextColumn::make('end_date')
                    ->label(__('app.fields.end_date'))
                    ->date(),
                TextColumn::make('created_at')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->emptyStateIcon(
                ! Member::exists()
                    ? 'heroicon-o-user-group'
                    : (! Plan::exists()
                        ? 'heroicon-o-pencil-square'
                        : 'heroicon-o-ticket'
                    )
            )
            ->emptyStateHeading(function ($livewire): string {
                if (! Member::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.members.plural')]);
                }

                if (! Plan::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.plans.plural')]);
                }

                $records = (string) __('app.resources.subscriptions.plural');
                $tab = (string) ($livewire->activeTab ?? 'all');
                $status = $tab !== 'all' ? (string) __('app.status.'.$tab) : null;

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];

                if (! $from && ! $to) {
                    return $status
                        ? __('app.empty.no_status_records', ['status' => $status, 'records' => $records])
                        : __('app.empty.no_records', ['records' => $records]);
                }

                if ($tab === 'all') {
                    return __('app.empty.no_records_in_range', ['records' => $records]);
                }

                return Subscription::where('status', $tab)->exists()
                    ? __('app.empty.no_status_records_in_range', ['status' => $status, 'records' => $records])
                    : __('app.empty.no_status_records', ['status' => $status, 'records' => $records]);
            })
            ->emptyStateDescription(function ($livewire): string {
                if (! Member::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.members.singular')]);
                }

                if (! Plan::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.plans.singular')]);
                }

                $records = __('app.resources.subscriptions.plural');
                $record = __('app.resources.subscriptions.singular');
                $tab = (string) ($livewire->activeTab ?? 'all');
                $status = $tab !== 'all' ? (string) __('app.status.'.$tab) : null;

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];

                if (! $fromRaw && ! $toRaw) {
                    return $status
                        ? __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status])
                        : __('app.empty.create_to_get_started', ['resource' => $record]);
                }

                $from = $fromRaw ? Carbon::parse($fromRaw)->format('d-m-Y') : (string) __('app.common.the_beginning');
                $to = $toRaw ? Carbon::parse($toRaw)->format('d-m-Y') : (string) __('app.common.today');

                if ($tab === 'all') {
                    return __('app.empty.found_none_between', ['records' => $records, 'from' => $from, 'to' => $to]);
                }

                if (! Subscription::where('status', $tab)->exists()) {
                    return __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.subscriptions.singular')]))
                    ->visible(fn () => Member::exists() && Plan::exists()),
                Action::make('create_member')
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.members.singular')]))
                    ->url(fn () => route('filament.admin.resources.members.create'))
                    ->hidden(fn () => Member::exists()),
                Action::make('create_plan')
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.plans.singular')]))
                    ->url(fn () => route('filament.admin.resources.plans.create'))
                    ->hidden(fn () => Plan::exists()),
            ])
            ->filters([
                TrashedFilter::make(),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label(__('app.fields.date_from'))
                            ->native(false)
                            ->suffixIcon('heroicon-m-calendar-days'),
                        DatePicker::make('date_to')
                            ->label(__('app.fields.date_to'))
                            ->native(false)
                            ->suffixIcon('heroicon-m-calendar-days'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading')
                            ->label(__('app.actions.change_status'))
                            ->disabled()
                            ->color('gray')
                            ->hidden(fn ($record): bool => in_array($record->status?->value, ['expired', 'upcoming', 'renewed'], true)),
                        StatusAction::make('mark_expiring', Status::Expiring)
                            ->label(__('app.actions.mark_as_expiring'))
                            ->color('warning')
                            ->requiresConfirmation()
                            ->action(fn (Subscription $record) => tap($record, function ($r) {
                                $r->update(['status' => 'expiring']);
                                Notification::make()
                                    ->title(__('app.notifications.subscription_marked_as_expiring'))
                                    ->warning()
                                    ->send();
                            }))
                            ->visible(fn ($record): bool => $record->status?->value === 'ongoing'),
                        StatusAction::make('mark_expired', Status::Expired)
                            ->label(__('app.actions.mark_as_expired'))
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(fn (Subscription $record) => tap($record, function ($r) {
                                $r->update(['status' => 'expired']);
                                Notification::make()
                                    ->title(__('app.notifications.subscription_marked_as_expired'))
                                    ->danger()
                                    ->send();
                            }))
                            ->visible(fn ($record): bool => in_array($record->status?->value, ['ongoing', 'expiring'], true)),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        Action::make('renew')
                            ->label(__('app.actions.renew'))
                            ->icon('heroicon-m-arrow-path')
                            ->color('success')
                            ->modalHeading(__('app.actions.renew'))
                            ->modalSubmitActionLabel(__('app.actions.renew'))
                            ->modalWidth('6xl')
                            ->closeModalByClickingAway(false)
                            ->visible(function (Subscription $record): bool {
                                if (! in_array($record->status?->value, ['expiring', 'expired'], true)) {
                                    return false;
                                }

                                if ($record->renewals()->exists()) {
                                    return false;
                                }

                                $today = Carbon::today(AppConfig::timezone());

                                return ! Subscription::query()
                                    ->where('member_id', $record->member_id)
                                    ->where('start_date', '>', $today)
                                    ->exists();
                            })
                            ->schema(fn (Subscription $record): array => SubscriptionForm::renewSchema($record))
                            ->action(function (Subscription $record, array $data): void {
                                SubscriptionForm::handleRenew($record, $data);
                            }),
                        ViewAction::make()
                            ->url(fn ($record) => SubscriptionResource::getUrl('view', ['record' => $record])),
                        EditAction::make()
                            ->hiddenLabel()
                            ->hidden(fn ($record): bool => in_array($record->status?->value, ['expired', 'renewed'], true))
                            ->url(fn ($record) => SubscriptionResource::getUrl('edit', ['record' => $record])),
                        DeleteAction::make()->hiddenLabel(),
                    ])->dropdown(false),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
                ->withCount('renewals'));
    }
}
