<?php

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('subject_nl')->nullable();
            $table->string('subject_en')->nullable();
            $table->string('subject_fr')->nullable();
            $table->text('body_nl')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_fr')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = [];

        foreach (EmailTemplateRegistry::all() as $key => $definition) {
            $row = [
                'id' => (string) Str::uuid(),
                'key' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (['nl', 'en', 'fr'] as $locale) {
                $defaults = $definition['defaults'][$locale] ?? null;
                $row["subject_{$locale}"] = $defaults['subject'] ?? null;
                $row["body_{$locale}"] = $defaults['body'] ?? null;
            }

            $rows[] = $row;
        }

        if ($rows !== []) {
            DB::table('email_templates')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
