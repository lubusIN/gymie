<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->unique('subscription_id');
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('discount', 5, 2)->nullable();
            $table->decimal('tax', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->string('discount_note')->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->decimal('subscription_fee', 12, 2)->default(0);
            $table->enum('status', ['issued', 'paid', 'partial', 'overdue', 'refund', 'cancelled'])->default('issued');
            $table->softDeletes();
            $table->timestamps();
            $table->index('date');
            $table->index(['status', 'date']);
            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
