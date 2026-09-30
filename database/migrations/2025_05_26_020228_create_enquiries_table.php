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
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('contact')->nullable()->index();
            $table->date('date')->nullable()->default(now());
            $table->enum('gender', ['male', 'female', 'other'])->default('male')->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('pincode')->nullable();
            $table->enum('status', ['lead', 'follow_up_scheduled', 'member', 'lost'])->default('lead');
            $table->text('lost_reason')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->json('interested_in')->nullable();
            $table->string('source')->nullable();
            $table->string('goal')->nullable();
            $table->date('start_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
