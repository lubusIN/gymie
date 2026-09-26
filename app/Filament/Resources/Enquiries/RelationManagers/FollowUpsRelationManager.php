<?php

namespace App\Filament\Resources\Enquiries\RelationManagers;

use App\Filament\Resources\FollowUps\Schemas\FollowUpForm;
use App\Filament\Resources\FollowUps\Tables\FollowUpTable;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'followUps';

    protected static ?string $title = null;

    protected static string|BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.resources.follow_ups.plural');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Configure the fields used to create or edit a follow-up.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->schema(FollowUpForm::scheduleFields())->columns(1);
    }

    /**
     * Configure the enquiry's follow-up table.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns(FollowUpTable::getColumns())
            ->headerActions([
                CreateAction::make()
                    ->label(__('app.actions.new_follow_up'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('app.actions.new_follow_up'))
                    ->modalWidth('sm')
                    ->createAnother(false)
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'user_id' => $this->getOwnerRecord()->user_id,
                    ]),
            ])
            ->emptyStateIcon('heroicon-o-arrow-path-rounded-square')
            ->emptyStateHeading(__('app.empty.no_records', [
                'records' => __('app.resources.follow_ups.plural'),
            ]))
            ->emptyStateDescription(__('app.empty.create_to_get_started', [
                'resource' => __('app.resources.follow_ups.singular'),
            ]))
            ->filters(FollowUpTable::getTableFilters())
            ->recordActions(FollowUpTable::getTableActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
