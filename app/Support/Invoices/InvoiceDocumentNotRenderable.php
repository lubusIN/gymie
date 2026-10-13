<?php

namespace App\Support\Invoices;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use RuntimeException;

/**
 * Thrown when an invoice PDF cannot be rendered due to missing required data.
 */
final class InvoiceDocumentNotRenderable extends RuntimeException
{
    /**
     * @param  array{
     *   missing: list<string>,
     *   invoice: Invoice,
     *   member?: Member|null,
     *   subscription?: Subscription|null,
     *   plan?: Plan|null,
     *   settings?: array<string, mixed>,
     *   generated_at?: string,
     *   logo_data_uri?: string|null,
     * }  $viewData
     */
    public function __construct(public readonly array $viewData)
    {
        $missingFields = $this->missingFields();
        $missingText = $missingFields
            ? implode(', ', $missingFields)
            : 'Missing required data';

        parent::__construct("Invoice document cannot be rendered: {$missingText}");
    }

    /**
     * @return list<string>
     */
    public function missingFields(): array
    {
        return $this->viewData['missing'];
    }
}
