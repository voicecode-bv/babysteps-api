<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->boolean('auto_add_new_users')->default(false)->after('members_can_view_members');
            $table->index('auto_add_new_users');
        });
    }

    public function down(): void
    {
        Schema::table('circles', function (Blueprint $table) {
            $table->dropIndex(['auto_add_new_users']);
            $table->dropColumn('auto_add_new_users');
        });
    }
};
