<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UserTable;
use App\Models\User;
use App\Support\Filament\StatusBadge;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('app.resources.users.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.users.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'email',
            'contact',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        $details = [];

        if (filled($record->email)) {
            $details[__('app.fields.email')] = $record->email;
        }

        if (filled($record->contact)) {
            $details[__('app.fields.contact')] = $record->contact;
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
        return UserForm::configure($schema);
    }

    /**
     * Define the table for listing records in the resource.
     */
    public static function table(Table $table): Table
    {
        return UserTable::configure($table);
    }

    /**
     * Add infolist to the resource.
     */
    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
