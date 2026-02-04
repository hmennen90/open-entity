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
        Schema::create('llm_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Display name (e.g., "OpenRouter Claude", "Local Ollama")
            $table->string('driver'); // ollama, openai, openrouter, nvidia
            $table->string('model'); // Model identifier
            $table->text('api_key')->nullable(); // Encrypted API key
            $table->string('base_url')->nullable(); // For Ollama or custom endpoints
            $table->boolean('is_active')->default(true); // Is this config enabled?
            $table->boolean('is_default')->default(false); // Is this the primary model?
            $table->integer('priority')->default(0); // Higher = tried first for fallback
            $table->json('options')->nullable(); // temperature, max_tokens, etc.
            $table->text('last_error')->nullable(); // Last error message
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->integer('error_count')->default(0); // For circuit breaker
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_configurations');
    }
};
