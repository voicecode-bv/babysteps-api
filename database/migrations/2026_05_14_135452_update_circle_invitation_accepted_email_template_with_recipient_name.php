<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $changes = [
            'body_nl' => [
                'from' => "# Goed nieuws!\n\n{accepted_by_name} heeft je uitnodiging geaccepteerd en is lid geworden van de kring \"{circle_name}\".",
                'to' => "# Hallo {recipient_name}!\n\nGoed nieuws: {accepted_by_name} heeft je uitnodiging geaccepteerd en is lid geworden van de kring \"{circle_name}\".",
            ],
            'body_en' => [
                'from' => "# Good news!\n\n{accepted_by_name} has accepted your invitation and joined the circle \"{circle_name}\".",
                'to' => "# Hello {recipient_name}!\n\nGood news: {accepted_by_name} has accepted your invitation and joined the circle \"{circle_name}\".",
            ],
            'body_fr' => [
                'from' => "# Bonne nouvelle !\n\n{accepted_by_name} a accepté votre invitation et a rejoint le cercle \"{circle_name}\".",
                'to' => "# Bonjour {recipient_name} !\n\nBonne nouvelle : {accepted_by_name} a accepté votre invitation et a rejoint le cercle \"{circle_name}\".",
            ],
        ];

        foreach ($changes as $column => $pair) {
            DB::table('email_templates')
                ->where('key', 'circle_invitation_accepted')
                ->where($column, $pair['from'])
                ->update([
                    $column => $pair['to'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No-op: this migration only refreshes seeded defaults.
    }
};
