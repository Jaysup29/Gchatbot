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
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->string('gtix_user_id')->nullable();
            $table->string('gtix_employee_id')->nullable();
            $table->string('gtix_username')->nullable();
            $table->string('gtix_department')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_authenticated')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamps();
            
            $table->index(['session_id']);
            $table->index(['gtix_user_id']);
            $table->index(['is_authenticated']);
            $table->index(['last_activity_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
