<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('path');
            $table->string('type')->default('image');
            $table->string('status')->default('ready');
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_small_path')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'sort_order']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE post_media ADD COLUMN coordinates geography(Point, 4326)');
            DB::statement('CREATE INDEX post_media_coordinates_gist ON post_media USING GIST (coordinates)');
        } else {
            // Fallback for non-Postgres environments. Production runs on PG+PostGIS.
            Schema::table('post_media', function (Blueprint $table) {
                $table->json('coordinates')->nullable();
            });
        }

        $this->backfillFromPosts();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS post_media_coordinates_gist');
        }

        Schema::dropIfExists('post_media');
    }

    /**
     * Seed one post_media row per existing post (sort_order 0) so the new
     * relation is fully populated. Old clients keep reading the shadow
     * columns on posts; new clients read the relation. Both stay in sync via
     * the PostMedia observer going forward.
     */
    private function backfillFromPosts(): void
    {
        $isPostgres = DB::connection()->getDriverName() === 'pgsql';

        // On Postgres we copy the geography column directly with an
        // INSERT ... SELECT so we don't roundtrip every row through PHP.
        if ($isPostgres) {
            DB::statement('
                INSERT INTO post_media (
                    id, post_id, sort_order, path, type, status,
                    thumbnail_path, thumbnail_small_path,
                    taken_at, coordinates, created_at, updated_at
                )
                SELECT
                    gen_random_uuid(),
                    id,
                    0,
                    media_url,
                    media_type,
                    media_status,
                    thumbnail_url,
                    thumbnail_small_url,
                    taken_at,
                    coordinates,
                    created_at,
                    updated_at
                FROM posts
            ');

            return;
        }

        DB::table('posts')->orderBy('id')->chunkById(500, function ($posts): void {
            $rows = [];

            foreach ($posts as $post) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'post_id' => $post->id,
                    'sort_order' => 0,
                    'path' => $post->media_url,
                    'type' => $post->media_type,
                    'status' => $post->media_status,
                    'thumbnail_path' => $post->thumbnail_url,
                    'thumbnail_small_path' => $post->thumbnail_small_url,
                    'taken_at' => $post->taken_at,
                    'coordinates' => $post->coordinates,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ];
            }

            if ($rows !== []) {
                DB::table('post_media')->insert($rows);
            }
        });
    }
};
