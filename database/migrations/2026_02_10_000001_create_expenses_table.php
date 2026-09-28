<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('category', 100);
            $table->string('status', 30)->default('pending');
            $table->string('vendor')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['date']);
            $table->index(['category']);
            $table->index(['status', 'paid_at']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
