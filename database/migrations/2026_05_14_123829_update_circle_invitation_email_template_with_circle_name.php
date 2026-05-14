<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $previous = [
            'subject_nl' => '{inviter_name} heeft je uitgenodigd',
            'body_nl' => "# Hallo!\n\n{inviter_name} heeft je uitgenodigd om lid te worden van hun kringen.\n\nHeb je nog geen account? Registreer je dan eerst.\n\nGroet,\nInnerr",
            'subject_en' => '{inviter_name} has invited you',
            'body_en' => "# Hello!\n\n{inviter_name} has invited you to join their circles.\n\nIf you don't have an account yet, please register first.\n\nRegards,\nInnerr",
            'subject_fr' => '{inviter_name} vous a invité',
            'body_fr' => "# Bonjour !\n\n{inviter_name} vous a invité à rejoindre ses cercles.\n\nSi vous n'avez pas encore de compte, veuillez d'abord vous inscrire.\n\nCordialement,\nInnerr",
        ];

        $next = [
            'subject_nl' => '{inviter_name} heeft je uitgenodigd voor {circle_name}',
            'body_nl' => "# Hallo!\n\n{inviter_name} heeft je uitgenodigd om lid te worden van de kring \"{circle_name}\".\n\nHeb je nog geen account? Registreer je dan eerst.\n\nGroet,\nInnerr",
            'subject_en' => '{inviter_name} has invited you to {circle_name}',
            'body_en' => "# Hello!\n\n{inviter_name} has invited you to join the circle \"{circle_name}\".\n\nIf you don't have an account yet, please register first.\n\nRegards,\nInnerr",
            'subject_fr' => '{inviter_name} vous a invité à rejoindre {circle_name}',
            'body_fr' => "# Bonjour !\n\n{inviter_name} vous a invité à rejoindre le cercle « {circle_name} ».\n\nSi vous n'avez pas encore de compte, veuillez d'abord vous inscrire.\n\nCordialement,\nInnerr",
        ];

        foreach ($previous as $column => $oldValue) {
            DB::table('email_templates')
                ->where('key', 'circle_invitation')
                ->where($column, $oldValue)
                ->update([
                    $column => $next[$column],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No-op: this migration only refreshes seeded defaults.
    }
};
