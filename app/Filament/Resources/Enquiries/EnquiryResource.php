<?php

namespace App\Filament\Resources\Enquiries;

use App\Filament\Resources\Enquiries\Pages\CreateEnquiry;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Filament\Resources\Enquiries\Schemas\EnquiryForm;
use App\Filament\Resources\Enquiries\Schemas\EnquiryInfolist;
use App\Filament\Resources\Enquiries\Tables\EnquiryTable;
use App\Models\Enquiry;
use App\Support\Filament\GlobalSearchBadge;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/** @extends resource<Enquiry> */
class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('app.resources.enquiries.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.enquiries.plural');
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

    /**
     * @param  Builder<Enquiry>  $query
     */
    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $query->with(['user']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        assert($record instanceof Enquiry);
        $details = [];

        if (filled($record->contact)) {
            $details[__('app.fields.contact')] = $record->contact;
        }

        if (filled($record->start_by)) {
            $details[__('app.fields.start_by')] = $record->start_by->toDateString();
        }

        if ($record->user?->name) {
            $details[__('app.fields.handled_by')] = $record->user->name;
        }

        if ($record->status) {
            $details[__('app.fields.status')] = GlobalSearchBadge::status($record->status);
        }

        return $details;
    }

    /**
     * Define the form schema for the resource.
     */
    public static function form(Schema $schema): Schema
    {
        return EnquiryForm::configure($schema);
    }

    /**
     * Get the Filament table configuration for the list view.
     */
    public static function table(Table $table): Table
    {
        return EnquiryTable::configure($table);
    }

    /**
     * Add infolist to the resource.
     */
    public static function infolist(Schema $schema): Schema
    {
        return EnquiryInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnquiries::route('/'),
            'create' => CreateEnquiry::route('/create'),
            'edit' => EditEnquiry::route('/{record}/edit'),
            'view' => ViewEnquiry::route('/{record}'),
        ];
    }

    /**
     * @return Builder<Enquiry>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
