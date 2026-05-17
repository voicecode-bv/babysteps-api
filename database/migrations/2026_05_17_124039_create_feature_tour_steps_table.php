<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_tour_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('step');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_tour_steps');
    }
};
