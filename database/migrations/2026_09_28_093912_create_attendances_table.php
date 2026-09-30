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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checked_in_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visited_on');
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->unique(['member_id', 'visited_on']);
            $table->index(['member_id', 'checked_in_at']);
            $table->index(['visited_on', 'checked_in_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
