<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            // Embedding storage - BLOB for binary vector data
            $table->binary('embedding')->nullable()->after('context');
            $table->unsignedSmallInteger('embedding_dimensions')->nullable()->after('embedding');
            $table->string('embedding_model', 100)->nullable()->after('embedding_dimensions');
            $table->timestamp('embedded_at')->nullable()->after('embedding_model');

            // Memory layer classification
            $table->enum('layer', ['episodic', 'semantic', 'procedural'])->default('episodic')->after('type');

            // Consolidation tracking
            $table->boolean('is_consolidated')->default(false)->after('last_recalled_at');
            $table->foreignId('consolidated_into_id')->nullable()->after('is_consolidated');
            $table->timestamp('consolidated_at')->nullable()->after('consolidated_into_id');

            // Semantic tagging for categorization
            $table->json('semantic_tags')->nullable()->after('consolidated_at');

            // Index for efficient queries
            $table->index('layer');
            $table->index('is_consolidated');
            $table->index('embedded_at');
        });

        // Add foreign key constraint separately to avoid issues
        Schema::table('memories', function (Blueprint $table) {
            $table->foreign('consolidated_into_id')
                ->references('id')
                ->on('memories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropForeign(['consolidated_into_id']);
            $table->dropIndex(['layer']);
            $table->dropIndex(['is_consolidated']);
            $table->dropIndex(['embedded_at']);

            $table->dropColumn([
                'embedding',
                'embedding_dimensions',
                'embedding_model',
                'embedded_at',
                'layer',
                'is_consolidated',
                'consolidated_into_id',
                'consolidated_at',
                'semantic_tags',
            ]);
        });
    }
};
