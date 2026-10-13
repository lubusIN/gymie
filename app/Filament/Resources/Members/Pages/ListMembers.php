<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\Status;
use App\Filament\Exports\MemberExporter;
use App\Filament\Imports\MemberImporter;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Support\Filament\TableUi;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
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
        return TableUi::statusTabs(Member::class, [Status::Active, Status::Inactive]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.memberships'),
            MemberResource::getUrl('index') => MemberResource::getNavigationLabel(),
        ];
    }
}
