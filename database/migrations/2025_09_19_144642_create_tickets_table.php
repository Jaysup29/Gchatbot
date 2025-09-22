<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('control_no')->unique();
            $table->foreignId('session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->string('gtix_user_id');
            $table->string('gtix_employee_id');
            $table->string('gtix_username');
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('category', ['technical', 'billing', 'general', 'feature_request']);
            $table->string('requester_email')->nullable();
            $table->string('requester_phone')->nullable();
            $table->timestamps();
            
            $table->index(['control_no']);
            $table->index(['gtix_user_id']);
            $table->index(['session_id']);
            $table->index(['category', 'priority']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
