<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\Status;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Subscriptions\Schemas\SubscriptionRenewalForm;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Helpers\Helpers;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionRenewalService;
use App\Support\AppConfig;
use App\Support\Filament\StatusAction;
use App\Support\Filament\TableUi;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->description(fn (Subscription $record): string => $record->member->code)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label(__('app.fields.plan'))
                    ->description(fn (Subscription $record): string => $record->plan->code)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label(__('app.fields.start_date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end_date')
                    ->label(__('app.fields.end_date'))
                    ->date()
                    ->description(fn (Subscription $record): ?string => self::daysLeft($record))
                    ->sortable(),
                TextColumn::make('invoice.due_amount')
                    ->label(__('app.fields.due'))
                    ->state(fn (Subscription $record): ?float => $record->invoice
                        ? (float) $record->invoice->due_amount
                        : null)
                    ->formatStateUsing(fn (?float $state): string => Helpers::formatCurrency($state ?? 0))
                    ->placeholder(__('app.placeholders.na'))
                    ->color(fn (?float $state): string => ($state ?? 0) > 0 ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('end_date')
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

                return TableUi::emptyStateHeading($livewire, Subscription::class, 'subscriptions');
            })
            ->emptyStateDescription(function ($livewire): string {
                if (! Member::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.members.singular')]);
                }

                if (! Plan::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.plans.singular')]);
                }

                return TableUi::emptyStateDescription($livewire, Subscription::class, 'subscriptions');
            })
            ->emptyStateActions([
                CreateAction::make()
                    ->visible(fn () => Member::exists() && Plan::exists()),
                Action::make('create_member')
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.members.singular')]))
                    ->url(fn () => route('filament.admin.resources.members.create'))
                    ->hidden(fn () => Member::exists()),
                Action::make('create_plan')
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.plans.singular')]))
                    ->url(fn (): string => PlanResource::getUrl('index'))
                    ->hidden(fn () => Plan::exists()),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('plan')
                    ->label(__('app.fields.plan'))
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                TableUi::dateRangeFilter('end_date'),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.fields.status'),
                    primaryActions: [
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
                    ],
                    recordActions: [
                        Action::make('renew')
                            ->label(__('app.actions.renew'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('success')
                            ->modalHeading(__('app.actions.renew'))
                            ->modalSubmitActionLabel(__('app.actions.renew'))
                            ->modalWidth(Width::SixExtraLarge)
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
                            ->schema(fn (Subscription $record): array => SubscriptionRenewalForm::schema($record))
                            ->action(function (Subscription $record, array $data, SubscriptionRenewalService $subscriptionRenewalService): void {
                                SubscriptionRenewalForm::handle($subscriptionRenewalService, $record, $data);
                            }),
                        ViewAction::make()
                            ->url(fn ($record) => SubscriptionResource::getUrl('view', ['record' => $record])),
                        EditAction::make()
                            ->hiddenLabel()
                            ->hidden(fn ($record): bool => in_array($record->status?->value, ['expired', 'renewed'], true))
                            ->url(fn ($record) => SubscriptionResource::getUrl('edit', ['record' => $record])),
                        DeleteAction::make()->hiddenLabel(),
                    ],
                    primaryVisible: fn (Subscription $record): bool => ! in_array(
                        $record->status?->value,
                        ['expired', 'upcoming', 'renewed'],
                        true,
                    ),
                ),
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
                ->with([
                    'invoice:id,subscription_id,due_amount,status',
                    'member:id,code,name',
                    'plan:id,code,name',
                ])
                ->withCount('renewals'));
    }

    /**
     * Describe the remaining membership time for active subscriptions.
     */
    private static function daysLeft(Subscription $subscription): ?string
    {
        $days = $subscription->daysRemaining();

        if ($days === null) {
            return null;
        }

        return $days.' '.__('app.widgets.days_left');
    }
}
