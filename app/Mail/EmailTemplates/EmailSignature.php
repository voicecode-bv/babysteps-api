<?php

namespace App\Mail\EmailTemplates;

class EmailSignature
{
    /** @var array<string, string> */
    private const TEMPLATES = [
        'nl' => "Groetjes,\n\n{innerr_name} van Innerr",
        'en' => "Cheers,\n\n{innerr_name} from Innerr",
        'fr' => "À bientôt,\n\n{innerr_name} de Innerr",
    ];

    /** @var array<int, string> */
    private const NAMES = ['Nicky', 'Michael'];

    public static function template(string $locale): string
    {
        return self::TEMPLATES[$locale] ?? self::TEMPLATES['en'];
    }

    public static function randomName(): string
    {
        return self::NAMES[array_rand(self::NAMES)];
    }
}
