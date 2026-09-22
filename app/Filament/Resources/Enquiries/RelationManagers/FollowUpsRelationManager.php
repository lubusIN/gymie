<?php

namespace App\Filament\Resources\Enquiries\RelationManagers;

use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Filament\Resources\FollowUps\Tables\FollowUpTable;
use App\Models\Enquiry;
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

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.titles.follow_up_timeline');
    }

    /**
     * Determine if the relation manager is read-only.
     *
     * @return bool Returns false, indicating the relation manager is not read-only.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define the form schema for the resource.
     */
    public function form(Schema $schema): Schema
    {
        return FollowUpResource::form($schema);
    }

    /**
     * Define the table for listing records in the resource.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns(FollowUpTable::getColumns())
            ->headerActions([
                CreateAction::make('create')
                    ->icon('heroicon-m-plus')
                    ->label(__('app.actions.new_follow_up'))
                    ->visible(function (): bool {
                        $ownerRecord = $this->getOwnerRecord();

                        return $ownerRecord instanceof Enquiry && $ownerRecord->followUps()->exists();
                    })
                    ->createAnother(false)
                    ->modalHeading(__('app.actions.new_follow_up'))
                    ->modalWidth('sm'),
            ])
            ->emptyStateIcon('heroicon-o-arrow-path-rounded-square')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.follow_ups.plural')]))
            ->emptyStateDescription(__('app.empty.create_to_get_started', ['resource' => __('app.resources.follow_ups.singular')]))
            ->emptyStateActions([
                CreateAction::make('create-follow-up')
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new_follow_up'))
                    ->createAnother(false)
                    ->modalHeading(__('app.actions.new_follow_up'))
                    ->modalWidth('sm'),
            ])
            ->filters(FollowUpTable::getTableFilters())
            ->recordActions(FollowUpTable::getTableActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
