<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.resources.subscriptions.plural');
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
        return SubscriptionResource::form($schema);
    }

    /**
     * Define the table for listing records in the resource.
     */
    public function table(Table $table): Table
    {
        return SubscriptionResource::table($table)
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('app.actions.new', ['resource' => __('app.resources.subscriptions.singular')]))
                    ->modalWidth('6xl')
                    ->closeModalByClickingAway(false)
                    ->createAnother(false),
            ]);
    }
}
