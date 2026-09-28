<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReceiptController extends Controller
{
    public function preview(Expense $expense): StreamedResponse
    {
        $this->authorize('view', $expense);
        abort_unless($expense->hasReceipt(), 404);

        return Storage::disk('local')->response(
            $expense->receipt_path,
            $expense->receiptFilename(),
        );
    }

    public function download(Expense $expense): StreamedResponse
    {
        $this->authorize('view', $expense);
        abort_unless($expense->hasReceipt(), 404);

        return Storage::disk('local')->download(
            $expense->receipt_path,
            $expense->receiptFilename(),
        );
    }
}
