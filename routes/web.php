<?php

use App\Http\Controllers\ExpenseReceiptController;
use App\Http\Controllers\InvoiceDocumentController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::middleware([Authenticate::class])
    ->group(function (): void {
        Route::get('/expenses/{expense}/receipt/preview', [ExpenseReceiptController::class, 'preview'])
            ->name('expenses.receipts.preview');

        Route::get('/expenses/{expense}/receipt/download', [ExpenseReceiptController::class, 'download'])
            ->name('expenses.receipts.download');

        Route::get('/invoices/{invoice}/preview', [InvoiceDocumentController::class, 'preview'])
            ->name('invoices.preview');

        Route::get('/invoices/{invoice}/download', [InvoiceDocumentController::class, 'download'])
            ->name('invoices.download');
    });
