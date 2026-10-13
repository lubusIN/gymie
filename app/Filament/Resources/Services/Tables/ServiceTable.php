<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceTable
{
    /**
     * Configure the service table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('app.fields.name'))
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->label(__('app.fields.description')),
                TextColumn::make('created_at')
                    ->searchable()
                    ->date('d-m-Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-cog-8-tooth')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.services.plural')]))
            ->emptyStateDescription(__('app.empty.create_to_get_started', ['resource' => __('app.resources.services.singular')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->modalHeading(__('app.actions.new', ['resource' => __('app.resources.services.singular')]))
                    ->modalWidth(Width::Small)
                    ->createAnother(false)
                    ->hidden(fn (): bool => Service::exists()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalCancelAction(false)
                    ->modalWidth(Width::Small),
                EditAction::make()->modalWidth(Width::Small),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
