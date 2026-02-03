<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('participant');
            $table->string('participant_type')->default('human'); // human, entity, system
            $table->string('channel')->default('web'); // web, moltbook, discord
            $table->text('summary')->nullable();
            $table->float('sentiment')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('participant');
            $table->index('channel');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
