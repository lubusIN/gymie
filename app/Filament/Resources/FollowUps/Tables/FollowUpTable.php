<?php

namespace App\Filament\Resources\FollowUps\Tables;

use App\Enums\Status;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\User;
use App\Support\Data;
use App\Support\Filament\StatusAction;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;

class FollowUpTable
{
    /**
     * Get the follow-up table column definitions.
     *
     * This is used by both the Follow Ups index table and any relation managers
     * that want to reuse the same column set.
     *
     * @return array<int, Column>
     */
    public static function getColumns(): array
    {
        return [
            TextColumn::make('id')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('enquiry.name')
                ->searchable()
                ->label(__('app.resources.enquiries.singular'))
                ->sortable(),
            TextColumn::make('user.name')
                ->searchable()
                ->label(__('app.fields.handled_by'))
                ->placeholder(__('app.placeholders.na'))
                ->sortable(),
            TextColumn::make('method')
                ->label(__('app.fields.method'))
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('schedule_date')
                ->searchable()
                ->date('d-m-Y')
                ->label(__('app.fields.schedule_date')),
            TextColumn::make('status')
                ->badge(),
            TextColumn::make('outcome')
                ->toggleable(isToggledHiddenByDefault: true)
                ->placeholder(__('app.placeholders.na'))
                ->limit(40)
                ->tooltip(function (TextColumn $column): ?string {
                    $state = Data::nullableString($column->getState());

                    if ($state === null || strlen($state) <= $column->getCharacterLimit()) {
                        return null;
                    }

                    return $state;
                }),
        ];
    }

    /**
     * Configure the follow-up table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::getColumns())
            ->emptyStateIcon(
                ! Enquiry::exists() ? 'heroicon-o-phone' : 'heroicon-o-arrow-path-rounded-square'
            )
            ->emptyStateHeading(function ($livewire): string {
                if (! Enquiry::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.enquiries.plural')]);
                }

                return TableUi::emptyStateHeading($livewire, FollowUp::class, 'follow_ups');
            })
            ->emptyStateDescription(function ($livewire): string {
                if (! Enquiry::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.enquiries.singular')]);
                }

                return TableUi::emptyStateDescription($livewire, FollowUp::class, 'follow_ups');
            })
            ->emptyStateActions([
                Action::make('create_enquiry')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.enquiries.singular')]))
                    ->url(fn () => route('filament.admin.resources.enquiries.create'))
                    ->icon('heroicon-o-plus')
                    ->hidden(fn () => Enquiry::exists()),
                CreateAction::make()
                    ->createAnother(false)
                    ->modalHeading(__('app.actions.new_follow_up'))
                    ->modalWidth(Width::Small)
                    ->visible(fn () => Enquiry::exists() && ! FollowUp::exists()),
            ])
            ->filters(static::getTableFilters())
            ->recordActions(static::getTableActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Get table filter definitions.
     *
     * @return array<int, Filter|TrashedFilter>
     */
    public static function getTableFilters(): array
    {
        return [
            TrashedFilter::make(),
            TableUi::dateRangeFilter(),
        ];
    }

    /**
     * Get table action definitions.
     *
     * @return array<int, ActionGroup>
     */
    public static function getTableActions(): array
    {
        return [
            TableUi::actionGroup(
                primaryLabel: __('app.fields.status'),
                primaryActions: [
                    StatusAction::make('mark_as_done', Status::Done)
                        ->color('success')
                        ->label(__('app.actions.mark_as_done'))
                        ->modalWidth(Width::Small)
                        ->fillForm(fn (FollowUp $record): array => [
                            'user_id' => $record->user_id,
                            'outcome' => $record->outcome,
                        ])
                        ->schema([
                            Select::make('user_id')
                                ->label(__('app.fields.handled_by'))
                                ->relationship(name: 'user', titleAttribute: 'name')
                                ->placeholder(__('app.placeholders.select_handler'))
                                ->getOptionLabelFromRecordUsing(function (User $record): string {
                                    $name = html_entity_decode($record->name, ENT_QUOTES, 'UTF-8');
                                    $url = ! empty($record->photo) ? e($record->photo) : "https://ui-avatars.com/api/?background=000&color=fff&name={$name}";

                                    return Blade::render(
                                        '<div class="flex items-center gap-2 h-9">
                                                <x-filament::avatar src="{{ $url }}" alt="{{ $name }}" size="sm" />
                                                <span class="ml-2">{{ $name }}</span>
                                            </div>',
                                        compact('url', 'name')
                                    );
                                })
                                ->allowHtml()
                                ->required(),
                            Textarea::make('outcome')
                                ->placeholder(__('app.placeholders.outcome_example'))
                                ->label(__('app.fields.outcome'))
                                ->required(),
                        ])
                        ->action(function (array $data, FollowUp $record): void {
                            $record->update([
                                'user_id' => $data['user_id'],
                                'outcome' => $data['outcome'],
                                'status' => 'done',
                            ]);
                            Notification::make()
                                ->title(__('app.notifications.follow_up_marked_as_done'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record->status->value === 'pending'),
                ],
                recordActions: [
                    ViewAction::make()
                        ->modalWidth(Width::Large)
                        ->modalCancelAction(false)
                        ->modalAlignment('center')
                        ->schema(
                            fn (Schema $schema): Schema => FollowUpResource::infolist($schema)
                        ),
                    EditAction::make()
                        ->modalWidth(Width::Small)
                        ->hidden(fn ($record): bool => $record->status->value === 'done'),
                    DeleteAction::make(),
                ],
                primaryVisible: fn (FollowUp $record): bool => $record->status === Status::Pending,
            ),
        ];
    }
}
