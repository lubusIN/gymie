<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\Status;
use App\Filament\Exports\MemberExporter;
use App\Filament\Imports\MemberImporter;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->hidden(! Member::exists()),
            ImportAction::make()
                ->importer(MemberImporter::class)
                ->label(__('app.actions.import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->maxRows(5000)
                ->authorize('Import:Member'),
            ExportAction::make()
                ->exporter(MemberExporter::class)
                ->label(__('app.actions.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->maxRows(10000)
                ->authorize('Export:Member')
                ->columnMappingColumns(3),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('app.common.all')),
            'active' => Tab::make(__('app.status.active'))
                ->badge(Member::query()->where('status', 'active')->count())
                ->badgeColor(Status::Active->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'active')),
            'inactive' => Tab::make(__('app.status.inactive'))
                ->badge(Member::query()->where('status', 'inactive')->count())
                ->badgeColor(Status::Inactive->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'inactive')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            MemberResource::getUrl('index') => MemberResource::getNavigationLabel(),
        ];
    }
}
