<?php

namespace App\Mail\EmailTemplates;

class EmailSignature
{
    public const SENDER_NAME = 'Nicky';

    public const AVATAR_URL = 'https://mailing.innerr.app/images/nicky.jpg';

    /** @var array<string, array{greeting: string, role: string}> */
    private const TEMPLATES = [
        'nl' => ['greeting' => 'Groetjes,', 'role' => 'van Innerr'],
        'en' => ['greeting' => 'Cheers,', 'role' => 'from Innerr'],
        'fr' => ['greeting' => 'À bientôt,', 'role' => 'de Innerr'],
    ];

    public static function template(string $locale): string
    {
        $copy = self::TEMPLATES[$locale] ?? self::TEMPLATES['en'];

        $avatar = self::AVATAR_URL;
        $name = self::SENDER_NAME;
        $greeting = $copy['greeting'];
        $role = $copy['role'];

        return <<<HTML
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top: 32px; border-collapse: separate;">
<tr>
<td style="padding-right: 16px; vertical-align: middle;">
<img src="{$avatar}" alt="{$name}" width="56" height="56" style="display: block; width: 56px; height: 56px; border-radius: 9999px; -webkit-border-radius: 9999px; object-fit: cover;">
</td>
<td style="vertical-align: middle; font-family: 'DM Sans', Arial, sans-serif; color: #1A1F4A; line-height: 1.4; font-size: 16px;">
{$greeting}<br>
<strong>{$name} {$role}</strong>
</td>
</tr>
</table>
HTML;
    }
}
