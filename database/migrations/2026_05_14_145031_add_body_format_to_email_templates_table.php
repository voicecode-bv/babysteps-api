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
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->string('body_format')->default('markdown_message')->after('body_fr');
        });

        $htmlPath = base_path('early-adopters-mail.html');
        $bodyHtml = is_file($htmlPath) ? (string) file_get_contents($htmlPath) : '';

        DB::table('email_templates')->updateOrInsert(
            ['key' => 'early_adopters'],
            [
                'id' => (string) Str::uuid(),
                'subject_nl' => 'Welkom bij Innerr — early access',
                'subject_en' => null,
                'subject_fr' => null,
                'body_nl' => $bodyHtml,
                'body_en' => null,
                'body_fr' => null,
                'body_format' => 'raw_html',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'early_adopters')->delete();

        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn('body_format');
        });
    }
};
