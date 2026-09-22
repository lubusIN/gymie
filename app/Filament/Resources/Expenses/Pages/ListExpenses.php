<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Enums\Status;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Expense;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => ExpenseResource::getModelLabel()]))
                ->icon('heroicon-m-plus')
                ->modalHeading(__('app.actions.new', ['resource' => ExpenseResource::getModelLabel()]))
                ->createAnother(false)
                ->modalWidth(Width::ScreenLarge)
                ->closeModalByClickingAway(false)
                ->hidden(! Expense::exists()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.billing'),
            ExpenseResource::getNavigationLabel(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('app.common.all')),
            'pending' => Tab::make(__('app.status.pending'))
                ->badge(Expense::query()->where('status', 'pending')->count())
                ->badgeColor(Status::Pending->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending')),
            'paid' => Tab::make(__('app.status.paid'))
                ->badge(Expense::query()->where('status', 'paid')->count())
                ->badgeColor(Status::Paid->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'paid')),
            'overdue' => Tab::make(__('app.status.overdue'))
                ->badge(Expense::query()->where('status', 'overdue')->count())
                ->badgeColor(Status::Overdue->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'overdue')),
            'cancelled' => Tab::make(__('app.status.cancelled'))
                ->badge(Expense::query()->where('status', 'cancelled')->count())
                ->badgeColor(Status::Cancelled->getColor())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'cancelled')),
        ];
    }
}
