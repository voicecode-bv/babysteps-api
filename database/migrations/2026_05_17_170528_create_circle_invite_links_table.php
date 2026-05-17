<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_invite_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['circle_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_invite_links');
    }
};
