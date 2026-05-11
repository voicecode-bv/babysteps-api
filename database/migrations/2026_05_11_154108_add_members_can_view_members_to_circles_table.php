<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->boolean('members_can_view_members')->default(true)->after('members_can_invite');
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropColumn('members_can_view_members');
        });
    }
};
