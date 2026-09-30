<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Enums\ExpensePaymentMethod;
use App\Enums\Status;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Widgets\ExpenseOverviewWidget;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Services\Reporting\CsvDownloadService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => ExpenseResource::getModelLabel()]))
                ->icon('heroicon-o-plus')
                ->modalHeading(__('app.actions.new', ['resource' => ExpenseResource::getModelLabel()]))
                ->createAnother(false)
                ->modalWidth(Width::ScreenLarge)
                ->closeModalByClickingAway(false)
                ->hidden(! Expense::exists()),
            Action::make('export_csv')
                ->label(__('app.actions.export_csv'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ExpenseOverviewWidget::class,
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

    /**
     * Export the expenses represented by the active table state.
     */
    private function exportCsv(): StreamedResponse
    {
        return app(CsvDownloadService::class)->download(
            query: $this->getTableQueryForExport(),
            filename: 'expenses-'.now()->toDateString().'.csv',
            headings: [
                __('app.fields.expense_name'),
                __('app.fields.category'),
                __('app.fields.vendor'),
                __('app.fields.date'),
                __('app.fields.due_date'),
                __('app.fields.paid_at'),
                __('app.fields.status'),
                __('app.fields.amount'),
                __('app.fields.payment_method'),
                __('app.fields.payment_reference'),
            ],
            row: fn (Expense $expense): array => [
                $expense->name,
                Helpers::getExpenseCategoryLabel($expense->category),
                $expense->vendor,
                $expense->date?->toDateString(),
                $expense->due_date?->toDateString(),
                $expense->paid_at?->format('Y-m-d H:i:s'),
                $expense->status?->getLabel(),
                (float) $expense->amount,
                ExpensePaymentMethod::tryFrom((string) $expense->payment_method)?->getLabel(),
                $expense->payment_reference,
            ],
        );
    }
}
