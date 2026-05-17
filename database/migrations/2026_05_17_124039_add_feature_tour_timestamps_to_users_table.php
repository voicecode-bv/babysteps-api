<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('feature_tour_started_at')->nullable()->after('onboarded_at');
            $table->timestamp('feature_tour_completed_at')->nullable()->after('feature_tour_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['feature_tour_started_at', 'feature_tour_completed_at']);
        });
    }
};
