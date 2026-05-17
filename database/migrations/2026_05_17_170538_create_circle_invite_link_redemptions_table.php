<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_invite_link_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invite_link_id')
                ->constrained('circle_invite_links')
                ->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('redeemed_at');

            $table->unique(['invite_link_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_invite_link_redemptions');
    }
};
