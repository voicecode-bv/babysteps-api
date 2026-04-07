<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('media_type');
            $table->unsignedInteger('comments_count')->default(0)->after('likes_count');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('body');
        });

        DB::statement('UPDATE posts SET likes_count = (SELECT COUNT(*) FROM likes WHERE likes.likeable_id = posts.id AND likes.likeable_type = ?)', ['App\\Models\\Post']);
        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement('UPDATE comments SET likes_count = (SELECT COUNT(*) FROM likes WHERE likes.likeable_id = comments.id AND likes.likeable_type = ?)', ['App\\Models\\Comment']);
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['likes_count', 'comments_count']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('likes_count');
        });
    }
};
