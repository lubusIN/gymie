<?php

namespace App\Filament\Resources\WhatsappTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class WhatsappTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('language')->searchable(),
                TextColumn::make('category')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'APPROVED' => 'success',
                        'REJECTED', 'DISABLED' => 'danger',
                        'PENDING', 'PAUSED' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('meta_template_id')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('delete')
                    ->requiresConfirmation()
                    ->action(function (\App\Models\WhatsappTemplate $record) {
                        $client = new \App\Services\WhatsApp\WhatsAppClient();
                        $client->deleteTemplate($record->name);
                        $record->delete();
                    })
                    ->color('danger')
                    ->icon('heroicon-o-trash'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
