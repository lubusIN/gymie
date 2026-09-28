<?php

namespace App\Models;

use App\Enums\Status;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'amount',
        'date',
        'due_date',
        'paid_at',
        'category',
        'status',
        'vendor',
        'payment_method',
        'payment_reference',
        'notes',
        'receipt_path',
    ];

    protected static function booted(): void
    {
        static::updated(function (Expense $expense): void {
            if (! $expense->wasChanged('receipt_path')) {
                return;
            }

            $previousReceiptPath = $expense->getOriginal('receipt_path');

            if (is_string($previousReceiptPath) && self::isReceiptPath($previousReceiptPath)) {
                Storage::disk('local')->delete($previousReceiptPath);
            }
        });

        static::forceDeleted(function (Expense $expense): void {
            if ($expense->hasReceipt()) {
                Storage::disk('local')->delete($expense->receipt_path);
            }
        });
    }

    public function hasReceipt(): bool
    {
        return is_string($this->receipt_path)
            && self::isReceiptPath($this->receipt_path)
            && Storage::disk('local')->exists($this->receipt_path);
    }

    public function receiptFilename(): string
    {
        $extension = pathinfo((string) $this->receipt_path, PATHINFO_EXTENSION);
        $allowedExtensions = ['jpeg', 'jpg', 'pdf', 'png', 'webp'];

        if (! in_array(strtolower($extension), $allowedExtensions, true)) {
            $extension = 'bin';
        }

        return "expense-{$this->getKey()}-receipt.{$extension}";
    }

    private static function isReceiptPath(string $path): bool
    {
        return str_starts_with($path, 'expenses/receipts/');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'status' => Status::class,
        ];
    }
}
