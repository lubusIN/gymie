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
        Schema::create('communication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('related');
            $table->string('channel')->default('email');
            $table->string('recipient');
            $table->string('subject', 150)->nullable();
            $table->string('status', 20);
            $table->boolean('is_test')->default(false);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->longText('retry_payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'queued_at'], 'comm_del_status_queued_idx');
            $table->index(['communication_template_id', 'queued_at'], 'comm_del_template_queued_idx');
            $table->index(['recipient', 'queued_at'], 'comm_del_recipient_queued_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_deliveries');
    }
};
