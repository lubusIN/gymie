<?php

use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Users\UserResource;

it('localizes Filament resource labels', function (string $locale, array $expected): void {
    app()->setLocale($locale);

    expect(__('filament-panels::layout.direction'))->toBe($expected['panel_direction']);

    expect(PlanResource::getModelLabel())->toBe($expected['plan_singular']);
    expect(PlanResource::getNavigationLabel())->toBe($expected['plan_plural']);

    expect(ServiceResource::getModelLabel())->toBe($expected['service_singular']);
    expect(ServiceResource::getNavigationLabel())->toBe($expected['service_plural']);

    expect(UserResource::getModelLabel())->toBe($expected['user_singular']);
    expect(UserResource::getNavigationLabel())->toBe($expected['user_plural']);

    expect(__('app.actions.edit', ['resource' => PlanResource::getModelLabel()]))->toBe($expected['edit_plan']);
    expect(__('app.titles.invoice_number', ['number' => 'INV-1']))->toBe($expected['invoice_title']);
    expect(__('app.widgets.net_revenue'))->toBe($expected['net_revenue']);
    expect(__('app.deletion_prevention.cannot_delete_title', ['module' => 'Invoice']))->toBe($expected['cannot_delete_invoice']);
})->with([
    'fr' => ['fr', [
        'panel_direction' => 'ltr',
        'plan_singular' => 'Forfait',
        'plan_plural' => 'Forfaits',
        'service_singular' => 'Service',
        'service_plural' => 'Services',
        'user_singular' => 'Membre du personnel',
        'user_plural' => 'Personnel',
        'edit_plan' => 'Modifier Forfait',
        'invoice_title' => 'Facture No. #INV-1',
        'net_revenue' => 'Revenu net',
        'cannot_delete_invoice' => 'Suppression impossible Invoice',
    ]],
    'ar' => ['ar', [
        'panel_direction' => 'ltr',
        'plan_singular' => 'خطة',
        'plan_plural' => 'الخطط',
        'service_singular' => 'خدمة',
        'service_plural' => 'الخدمات',
        'user_singular' => 'موظف',
        'user_plural' => 'الموظفون',
        'edit_plan' => 'تعديل خطة',
        'invoice_title' => 'فاتورة رقم INV-1',
        'net_revenue' => 'صافي الايراد',
        'cannot_delete_invoice' => 'لا يمكن حذف Invoice',
    ]],
]);
