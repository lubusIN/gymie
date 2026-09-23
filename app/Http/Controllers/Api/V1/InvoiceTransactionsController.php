<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\InvoiceTransactionStoreRequest;
use App\Http\Resources\V1\InvoiceTransactionResource;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use App\Services\Api\QueryFilters;
use App\Services\Invoices\InvoiceTransactionService;
use App\Support\AppConfig;
use App\Support\Data;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Nested invoice transaction endpoints.
 */
class InvoiceTransactionsController extends ApiController
{
    /**
     * List transactions for an invoice.
     */
    public function index(Request $request, Invoice $invoice): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'View:Invoice');

        $perPage = QueryFilters::perPage($request, default: 25);

        $transactions = $invoice->transactions()
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        return InvoiceTransactionResource::collection($transactions);
    }

    /**
     * Create a payment/refund transaction for an invoice.
     */
    public function store(
        InvoiceTransactionStoreRequest $request,
        Invoice $invoice,
        InvoiceTransactionService $transactionService,
    ): InvoiceTransactionResource {
        $this->requirePermission($request, 'Update:Invoice');

        $data = $request->validated();

        $transaction = $transactionService->record($invoice, [
            'type' => $data['type'],
            'amount' => $data['amount'],
            'occurred_at' => $data['occurred_at'] ?? now()->timezone(AppConfig::timezone()),
            'payment_method' => $data['payment_method'] ?? $invoice->payment_method,
            'note' => $data['note'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'created_by' => Data::int($this->currentUser($request)->getAuthIdentifier(), 0),
        ]);

        return new InvoiceTransactionResource($transaction);
    }

    /**
     * Delete a transaction belonging to an invoice.
     */
    public function destroy(
        Request $request,
        Invoice $invoice,
        InvoiceTransaction $transaction,
        InvoiceTransactionService $transactionService,
    ): JsonResponse {
        $this->requirePermission($request, 'Update:Invoice');

        abort_unless((int) $transaction->invoice_id === (int) $invoice->id, 404);

        $transactionService->delete($invoice, $transaction);

        return $this->noContent();
    }
}
