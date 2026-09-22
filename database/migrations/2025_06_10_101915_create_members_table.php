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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('photo')->nullable();
            $table->string('code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('contact')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('health_issue')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->default('other')->nullable();
            $table->date('dob')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('pincode')->nullable();
            $table->string('source')->default('promotions')->nullable();
            $table->string('goal')->default('fitness')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
