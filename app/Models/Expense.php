<?php

namespace App\Models;

use App\Enums\Status;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

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
        'notes',
    ];

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
