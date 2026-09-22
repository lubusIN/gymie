<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\InvoiceStoreRequest;
use App\Http\Requests\Api\V1\InvoiceUpdateRequest;
use App\Http\Resources\V1\InvoiceResource;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Api\QueryFilters;
use App\Support\Data;
use App\Support\Invoices\InvoiceDocument;
use App\Support\Invoices\InvoiceDocumentNotRenderable;
use App\Support\Invoices\InvoicePdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Invoices CRUD endpoints.
 */
class InvoicesController extends ApiController
{
    private const RESOURCE_KEY = 'invoices';

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Invoice');

        $query = Invoice::query()
            ->with(['subscription.member', 'subscription.plan']);

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request);

        return InvoiceResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created invoice.
     */
    public function store(InvoiceStoreRequest $request): InvoiceResource
    {
        $this->requirePermission($request, 'Create:Invoice');

        $data = $request->validated();

        /** @var Subscription $subscription */
        $subscription = Subscription::query()
            ->with('plan')
            ->findOrFail(Data::int($data['subscription_id'] ?? null));

        $data['due_date'] = $data['due_date'] ?? $data['date'];
        $data['status'] = $data['status'] ?? 'issued';

        if (! array_key_exists('subscription_fee', $data) || $data['subscription_fee'] === null) {
            $data['subscription_fee'] = $subscription->plan
                ? (float) $subscription->plan->amount
                : 0.0;
        }

        $invoice = Invoice::create($data);
        $invoice->load(['subscription.member', 'subscription.plan']);

        return new InvoiceResource($invoice);
    }

    /**
     * Display an invoice.
     */
    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->requirePermission($request, 'View:Invoice');

        $invoice->load(['subscription.member', 'subscription.plan']);

        return new InvoiceResource($invoice);
    }

    /**
     * Update an invoice.
     */
    public function update(InvoiceUpdateRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->requirePermission($request, 'Update:Invoice');

        $invoice->update($request->validated());
        $invoice->load(['subscription.member', 'subscription.plan']);

        return new InvoiceResource($invoice);
    }

    /**
     * Soft delete an invoice.
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->deleteModel($request, 'Delete:Invoice', $invoice);
    }

    /**
     * Restore a soft deleted invoice.
     */
    public function restore(Request $request, int $invoice): InvoiceResource
    {
        $record = $this->restoreSoftDeleted($request, 'RestoreAny:Invoice', Invoice::class, $invoice);
        $record->load(['subscription.member', 'subscription.plan']);

        return new InvoiceResource($record->refresh());
    }

    /**
     * Permanently delete an invoice.
     */
    public function forceDelete(Request $request, int $invoice): JsonResponse
    {
        $this->forceDeleteSoftDeleted($request, 'ForceDeleteAny:Invoice', Invoice::class, $invoice);

        return $this->noContent();
    }

    /**
     * Render an invoice as an inline PDF.
     */
    public function pdf(Request $request, Invoice $invoice, InvoicePdfRenderer $renderer): Response|JsonResponse
    {
        $this->requirePermission($request, 'View:Invoice');

        $invoice = InvoiceDocument::loadForRendering($invoice);

        try {
            $pdfBytes = $renderer->render($invoice);
        } catch (InvoiceDocumentNotRenderable $exception) {
            return response()->json([
                'message' => 'Invoice can’t be generated.',
                'missing' => $exception->viewData['missing'],
            ], 422);
        }

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.InvoiceDocument::pdfFilename($invoice).'"',
        ]);
    }

    /**
     * Download an invoice as a PDF attachment.
     */
    public function downloadPdf(Request $request, Invoice $invoice, InvoicePdfRenderer $renderer): Response|JsonResponse
    {
        $this->requirePermission($request, 'View:Invoice');

        $invoice = InvoiceDocument::loadForRendering($invoice);

        try {
            $pdfBytes = $renderer->render($invoice);
        } catch (InvoiceDocumentNotRenderable $exception) {
            return response()->json([
                'message' => 'Invoice can’t be generated.',
                'missing' => $exception->viewData['missing'],
            ], 422);
        }

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.InvoiceDocument::pdfFilename($invoice).'"',
        ]);
    }
}
