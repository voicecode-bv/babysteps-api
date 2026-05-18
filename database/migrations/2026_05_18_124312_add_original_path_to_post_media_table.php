<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            // Voor HLS-posts wijst `path` naar master.m3u8 maar het origineel
            // ligt los onder `users/{uid}/originals/posts/{filename}.{ext}` —
            // die mapping kan niet uit het display-pad worden afgeleid. We
            // slaan 'm expliciet op zodat PostResource een werkende
            // `original_url` kan teruggeven. Voor mp4/foto posts blijft NULL
            // en valt de resource terug op de bestaande heuristiek.
            $table->string('original_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            $table->dropColumn('original_path');
        });
    }
};
