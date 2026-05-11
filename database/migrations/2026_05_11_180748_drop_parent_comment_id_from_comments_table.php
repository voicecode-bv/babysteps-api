<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS comments_post_id_parent_comment_id_index');
        DB::statement('DROP INDEX IF EXISTS comments_parent_comment_id_index');

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('parent_comment_id');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignUuid('parent_comment_id')
                ->nullable()
                ->after('post_id')
                ->constrained('comments')
                ->cascadeOnDelete();

            $table->index(['post_id', 'parent_comment_id']);
            $table->index('parent_comment_id');
        });
    }
};
