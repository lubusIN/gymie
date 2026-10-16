<?php

namespace App\Filament\Resources\FollowUps;

use App\Filament\Resources\FollowUps\Pages\ListFollowUps;
use App\Filament\Resources\FollowUps\Schemas\FollowUpForm;
use App\Filament\Resources\FollowUps\Schemas\FollowUpInfolist;
use App\Filament\Resources\FollowUps\Tables\FollowUpTable;
use App\Models\FollowUp;
use App\Support\Filament\StatusBadge;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FollowUpResource extends Resource
{
    protected static ?string $model = FollowUp::class;

    public static function getModelLabel(): string
    {
        return __('app.resources.follow_ups.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.follow_ups.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'enquiry.name',
            'user.name',
            'method',
            'outcome',
        ];
    }

    /**
     * @param  Builder<FollowUp>  $query
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->with(['enquiry', 'user']);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        assert($record instanceof FollowUp);
        $title = $record->enquiry?->name;

        if (blank($title)) {
            return static::getModelLabel();
        }

        return (string) $title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        assert($record instanceof FollowUp);
        $details = [];

        if ($record->schedule_date) {
            $details[__('app.fields.schedule_date')] = $record->schedule_date->toDateString();
        }

        if ($record->user?->name) {
            $details[__('app.fields.handled_by')] = $record->user->name;
        }

        if (filled($record->method)) {
            $details[__('app.fields.method')] = $record->method;
        }

        if ($record->status) {
            $details[__('app.fields.status')] = StatusBadge::render($record->status);
        }

        return $details;
    }

    /**
     * Define the form schema for the resource.
     */
    public static function form(Schema $schema): Schema
    {
        return FollowUpForm::configure($schema);
    }

    /**
     * Get the Filament table configuration for the list view.
     */
    public static function table(Table $table): Table
    {
        return FollowUpTable::configure($table);
    }

    /**
     * Add infolist to the resource.
     */
    public static function infolist(Schema $schema): Schema
    {
        return FollowUpInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowUps::route('/'),
        ];
    }
}
