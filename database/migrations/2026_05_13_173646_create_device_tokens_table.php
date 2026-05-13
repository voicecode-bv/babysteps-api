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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_used_at']);
        });

        DB::table('users')
            ->whereNotNull('fcm_token')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                $now = now();
                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'token' => $user->fcm_token,
                        'platform' => null,
                        'last_used_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('device_tokens')->insertOrIgnore($rows);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('locale');
        });

        Schema::dropIfExists('device_tokens');
    }
};
