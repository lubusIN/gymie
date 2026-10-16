<?php

namespace App\Filament\Resources\Enquiries\Tables;

use App\Enums\Status;
use App\Models\Enquiry;
use App\Support\Filament\EnquiryPipelineActions;
use App\Support\Filament\TableUi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnquiryTable
{
    /**
     * Configure the enquiry table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'member',
                'nextFollowUp',
                'user',
            ]))
            ->columns([
                TextColumn::make('id')->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.id')),
                TextColumn::make('name')->searchable()->label(__('app.fields.name')),
                TextColumn::make('contact')->searchable()->label(__('app.fields.contact')),
                TextColumn::make('user.name')
                    ->label(__('app.fields.lead_owner'))
                    ->searchable(),
                TextColumn::make('source')
                    ->label(__('app.fields.source'))
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (string) __('app.options.source.'.$state)
                        : (string) __('app.placeholders.na')),
                TextColumn::make('nextFollowUp.schedule_date')
                    ->label(__('app.fields.next_follow_up'))
                    ->date('d-m-Y')
                    ->placeholder(__('app.placeholders.na'))
                    ->color(fn (Enquiry $record): ?string => $record->nextFollowUp?->schedule_date?->isBefore(today())
                        ? 'danger'
                        : null),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.email')),
                TextColumn::make('date')->sortable()->date('d-m-Y')->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.date')),
                TextColumn::make('start_by')->date('d-m-Y')->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.start_by')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('app.fields.status')),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-phone')
            ->emptyStateHeading(fn ($livewire): string => TableUi::emptyStateHeading($livewire, Enquiry::class, 'enquiries'))
            ->emptyStateDescription(fn ($livewire): string => TableUi::emptyStateDescription($livewire, Enquiry::class, 'enquiries'))
            ->emptyStateActions([
                CreateAction::make()
                    ->hidden(fn (): bool => Enquiry::exists()),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label(__('app.fields.lead_owner'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label(__('app.fields.source'))
                    ->options([
                        'promotions' => __('app.options.source.promotions'),
                        'word_of_mouth' => __('app.options.source.word_of_mouth'),
                        'others' => __('app.options.source.others'),
                    ]),
                TableUi::dateRangeFilter('date'),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.fields.status'),
                    primaryActions: [
                        EnquiryPipelineActions::scheduleFollowUp(),
                        EnquiryPipelineActions::convertToMember(),
                        EnquiryPipelineActions::markAsLost(),
                    ],
                    recordActions: [
                        EnquiryPipelineActions::viewMember(),
                        EditAction::make()->hiddenLabel(),
                        DeleteAction::make()->hiddenLabel(),
                    ],
                    primaryVisible: fn (Enquiry $record): bool => ! in_array(
                        $record->status,
                        [Status::Member, Status::Lost],
                        true,
                    ),
                ),
            ])->recordUrl(fn ($record): string => route('filament.admin.resources.enquiries.view', $record->id))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
