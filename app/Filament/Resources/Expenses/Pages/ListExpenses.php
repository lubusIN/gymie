<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Enums\ExpensePaymentMethod;
use App\Enums\Status;
use App\Filament\Pages\ListRecordsWithTableTabs;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Widgets\ExpenseOverviewWidget;
use App\Helpers\Helpers;
use App\Models\Expense;
use App\Services\Reporting\CsvDownloadService;
use App\Support\Filament\HasDateRangeFilters;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListExpenses extends ListRecordsWithTableTabs
{
    use HasDateRangeFilters;
    use HasFilters;

    protected static string $resource = ExpenseResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->initializeDateRangeFilters();
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.date-range-header', [
            'page' => $this,
            'dateRangeForm' => $this->dateRangeForm,
        ]);
    }

    public function dateRangeForm(Schema $schema): Schema
    {
        return $this->configureDateRangeFilters($schema);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(
            fn (Builder $query): Builder => $this->applyDateRangeToQuery($query),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label(__('app.actions.export_csv'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            CreateAction::make()
                ->modalHeading(__('app.actions.new', ['resource' => ExpenseResource::getModelLabel()]))
                ->createAnother(false)
                ->modalWidth(Width::ScreenLarge)
                ->closeModalByClickingAway(false)
                ->hidden(! Expense::exists()),
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
        return TableUi::statusTabs(Expense::class, [
            Status::Pending,
            Status::Paid,
            Status::Overdue,
            Status::Cancelled,
        ], fn (Builder $query): Builder => $this->applyDateRangeToQuery($query));
    }

    protected function supportsAllDateRange(): bool
    {
        return true;
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
