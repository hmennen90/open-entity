<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // experience, conversation, learned, social
            $table->text('content');
            $table->text('summary')->nullable();
            $table->float('importance')->default(0.5);
            $table->float('emotional_valence')->default(0.0); // -1.0 to 1.0
            $table->json('context')->nullable();
            $table->string('related_entity')->nullable();
            $table->foreignId('thought_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('recalled_count')->default(0);
            $table->timestamp('last_recalled_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('importance');
            $table->index('related_entity');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
