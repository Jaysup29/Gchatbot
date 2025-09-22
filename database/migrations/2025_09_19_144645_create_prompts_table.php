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
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_phrase');
            $table->text('prompt_content');
            $table->enum('prompt_type', ['system', 'response', 'instruction'])->default('response');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'priority']);
            $table->index(['prompt_type', 'is_active']);
            $table->fullText(['trigger_phrase', 'prompt_content']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompts');
    }
};
