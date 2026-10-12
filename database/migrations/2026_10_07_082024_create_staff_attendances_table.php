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
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('checked_in_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visited_on');
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->unique(['staff_id', 'visited_on']);
            $table->index(['staff_id', 'checked_in_at']);
            $table->index(['visited_on', 'checked_in_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
