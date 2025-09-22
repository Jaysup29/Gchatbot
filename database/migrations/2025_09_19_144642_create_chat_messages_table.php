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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('chat_sessions')->onDelete('cascade');
            $table->enum('sender_type', ['user', 'assistant', 'system']);
            $table->text('message_content');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
            
            $table->index(['session_id', 'sent_at']);
            $table->index(['sender_type', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
