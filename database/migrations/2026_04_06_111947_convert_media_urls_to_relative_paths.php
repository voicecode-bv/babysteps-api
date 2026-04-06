<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $storageUrl = Storage::disk('public')->url('');

        DB::table('posts')
            ->where('media_url', 'like', '%/storage/%')
            ->update([
                'media_url' => DB::raw("REPLACE(media_url, '{$storageUrl}', '')"),
            ]);

        DB::table('users')
            ->whereNotNull('avatar')
            ->where('avatar', 'like', '%/storage/%')
            ->update([
                'avatar' => DB::raw("REPLACE(avatar, '{$storageUrl}', '')"),
            ]);

        DB::table('notifications')
            ->where('data', 'like', '%/storage/%')
            ->update([
                'data' => DB::raw("REPLACE(data, '{$storageUrl}', '')"),
            ]);
    }

    public function down(): void
    {
        $storageUrl = Storage::disk('public')->url('');

        DB::table('posts')
            ->where('media_url', 'not like', '%http%')
            ->update([
                'media_url' => DB::raw("'{$storageUrl}' || media_url"),
            ]);

        DB::table('users')
            ->whereNotNull('avatar')
            ->where('avatar', 'not like', '%http%')
            ->update([
                'avatar' => DB::raw("'{$storageUrl}' || avatar"),
            ]);
    }
};
