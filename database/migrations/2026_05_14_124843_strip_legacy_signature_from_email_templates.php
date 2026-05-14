<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $suffixes = [
            'body_nl' => "\n\nGroet,\nInnerr",
            'body_en' => "\n\nRegards,\nInnerr",
            'body_fr' => "\n\nCordialement,\nInnerr",
        ];

        DB::table('email_templates')->orderBy('key')->lazy()->each(function (object $row) use ($suffixes): void {
            $updates = [];

            foreach ($suffixes as $column => $suffix) {
                $value = (string) ($row->{$column} ?? '');

                if ($value !== '' && str_ends_with($value, $suffix)) {
                    $updates[$column] = rtrim(substr($value, 0, -strlen($suffix)));
                }
            }

            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('email_templates')->where('id', $row->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        // No-op: stripping the legacy hardcoded sign-off is not reversible
        // without knowing whether it was originally added by the seed or by an admin.
    }
};
