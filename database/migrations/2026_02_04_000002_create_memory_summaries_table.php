<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_summaries', function (Blueprint $table) {
            $table->id();

            // Period this summary covers
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_type', 20); // 'daily', 'weekly', 'monthly'

            // Summary content
            $table->text('summary');
            $table->text('key_insights')->nullable();

            // Aggregated metrics
            $table->float('average_emotional_valence')->default(0.0);
            $table->json('themes')->nullable();
            $table->json('entities_mentioned')->nullable();

            // Embedding for semantic search
            $table->binary('embedding')->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->string('embedding_model', 100)->nullable();

            // Source tracking
            $table->integer('source_memory_count')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('period_type');
            $table->index(['period_start', 'period_end']);
            $table->unique(['period_type', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_summaries');
    }
};
