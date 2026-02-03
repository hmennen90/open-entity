<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thoughts', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->string('type')->default('observation'); // observation, reflection, decision, emotion, curiosity
            $table->string('trigger')->nullable();
            $table->json('context')->nullable();
            $table->float('intensity')->default(0.5);
            $table->boolean('led_to_action')->default(false);
            $table->text('action_taken')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thoughts');
    }
};
