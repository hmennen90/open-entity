<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('human'); // human, entity, group
            $table->string('platform')->nullable(); // web, moltbook, discord
            $table->float('familiarity')->default(0.0); // 0.0 to 1.0
            $table->float('affinity')->default(0.0); // -1.0 to 1.0
            $table->float('trust')->default(0.5); // 0.0 to 1.0
            $table->json('notes')->nullable();
            $table->json('known_facts')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->integer('interaction_count')->default(0);
            $table->timestamps();

            $table->index('name');
            $table->index('type');
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
