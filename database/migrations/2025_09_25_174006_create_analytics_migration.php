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
        // Create analytics table for daily metrics
        Schema::create('chat_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('active_sessions')->default(0);
            $table->integer('total_messages')->default(0);
            $table->integer('prompt_responses')->default(0);
            $table->integer('faq_responses')->default(0);
            $table->integer('ai_responses')->default(0);
            $table->decimal('avg_session_duration', 8, 2)->default(0); // in minutes
            $table->timestamps();
            
            $table->unique('date');
        });

        // Create prompt usage tracking
        Schema::create('prompt_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prompt_id');
            $table->date('date');
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->foreign('prompt_id')->references('id')->on('prompts')->onDelete('cascade');
            $table->unique(['prompt_id', 'date']);
        });

        // Create FAQ usage tracking
        Schema::create('faq_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('faq_id');
            $table->date('date');
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->foreign('faq_id')->references('id')->on('faqs')->onDelete('cascade');
            $table->unique(['faq_id', 'date']);
        });

        // Create popular questions tracking
        Schema::create('popular_questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('question_hash')->unique(); // hash of normalized question
            $table->integer('ask_count')->default(1);
            $table->date('last_asked');
            $table->timestamps();
        });

        // Add analytics columns to existing tables if needed
        if (!Schema::hasColumn('chat_sessions', 'started_at')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->integer('message_count')->default(0);
            });
        }

        if (!Schema::hasColumn('faqs', 'status')) {
            Schema::table('faqs', function (Blueprint $table) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
                $table->boolean('is_auto_generated')->default(false);
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('popular_questions');
        Schema::dropIfExists('faq_usage');
        Schema::dropIfExists('prompt_usage');
        Schema::dropIfExists('chat_analytics');

        // Remove added columns
        if (Schema::hasColumn('chat_sessions', 'started_at')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->dropColumn(['started_at', 'ended_at', 'message_count']);
            });
        }

        if (Schema::hasColumn('faqs', 'status')) {
            Schema::table('faqs', function (Blueprint $table) {
                $table->dropForeign(['approved_by']);
                $table->dropColumn(['status', 'is_auto_generated', 'approved_at', 'approved_by']);
            });
        }
    }
};