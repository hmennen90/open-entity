<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('motivation')->nullable();
            $table->string('type')->default('curiosity'); // curiosity, social, learning, creative, self-improvement
            $table->float('priority')->default(0.5);
            $table->string('status')->default('active'); // active, paused, completed, abandoned
            $table->float('progress')->default(0.0);
            $table->json('progress_notes')->nullable();
            $table->string('origin')->default('self'); // self, suggested, derived
            $table->timestamp('completed_at')->nullable();
            $table->text('abandoned_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
