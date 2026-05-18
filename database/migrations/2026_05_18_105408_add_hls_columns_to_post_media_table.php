<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            // 'mp4' = legacy single-rendition MP4 path, 'hls' = master.m3u8 +
            // variant playlists + segments. Default 'mp4' zodat bestaande rijen
            // hun huidige interpretatie houden zonder backfill.
            $table->string('format', 8)->default('mp4')->after('type');

            // FileFlux task_id voor idempotency op de webhook + reconcile-pad.
            $table->string('external_job_id')->nullable()->unique()->after('format');

            // Timestamp wanneer we de transcode submission deden, voor
            // ReconcileFileFluxJobs (markeert stuck Processing >30 min als Failed).
            $table->timestamp('processing_started_at')->nullable()->after('external_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('post_media', function (Blueprint $table) {
            $table->dropUnique(['external_job_id']);
            $table->dropColumn(['format', 'external_job_id', 'processing_started_at']);
        });
    }
};
