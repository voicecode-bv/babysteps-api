<?php

namespace Database\Seeders;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EmailTemplateRegistry::all() as $key => $definition) {
            $attributes = ['key' => $key];

            foreach (EmailTemplate::SUPPORTED_LOCALES as $locale) {
                $defaults = $definition['defaults'][$locale] ?? null;
                $attributes["subject_{$locale}"] = $defaults['subject'] ?? null;
                $attributes["body_{$locale}"] = $defaults['body'] ?? null;
            }

            EmailTemplate::query()->updateOrCreate(['key' => $key], $attributes);
        }
    }
}
