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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewed_from_subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->foreignId('member_id')
                ->constrained('members')
                ->onDelete('cascade');
            $table->foreignId('plan_id')
                ->constrained('plans')
                ->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['upcoming', 'ongoing', 'expiring', 'expired', 'renewed'])
                ->default('ongoing');
            $table->softDeletes();
            $table->timestamps();
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['status', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
