<?php

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Models\EmailTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = EmailTemplateRegistry::get(EmailTemplateRegistry::EARLY_ADOPTERS);

        if ($definition === null) {
            return;
        }

        $nl = $definition['defaults']['nl'] ?? ['subject' => '', 'body' => ''];

        DB::table('email_templates')
            ->where('key', EmailTemplateRegistry::EARLY_ADOPTERS)
            ->update([
                'body_format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'subject_nl' => $nl['subject'],
                'body_nl' => $nl['body'],
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $htmlPath = base_path('early-adopters-mail.html');
        $bodyHtml = is_file($htmlPath) ? (string) file_get_contents($htmlPath) : '';

        DB::table('email_templates')
            ->where('key', EmailTemplateRegistry::EARLY_ADOPTERS)
            ->update([
                'body_format' => EmailTemplate::FORMAT_RAW_HTML,
                'subject_nl' => 'Welkom bij Innerr — early access',
                'body_nl' => $bodyHtml,
                'updated_at' => now(),
            ]);
    }
};
