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
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('schedule_date')->nullable();
            $table->enum('method', ['call', 'email', 'in_person', 'whatsapp', 'other'])->default('call')->nullable();
            $table->string('outcome')->nullable();
            $table->enum('status', ['pending', 'done'])->default('pending')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['enquiry_id', 'status', 'schedule_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
