<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Support\Filament\HasStatusHeading;
use Filament\Resources\Pages\ViewRecord;

class ViewExpense extends ViewRecord
{
    use HasStatusHeading;

    protected static string $resource = ExpenseResource::class;

    public function getTitle(): string
    {
        return ExpenseResource::getModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.billing'),
            ExpenseResource::getUrl('index') => ExpenseResource::getNavigationLabel(),
            __('app.actions.view', ['resource' => ExpenseResource::getModelLabel()]),
        ];
    }
}
