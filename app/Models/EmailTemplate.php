<?php

namespace App\Models;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'subject_nl', 'subject_en', 'subject_fr',
    'body_nl', 'body_en', 'body_fr',
    'body_format',
])]
class EmailTemplate extends Model
{
    use HasUuids;

    public const SUPPORTED_LOCALES = ['nl', 'en', 'fr'];

    public const FALLBACK_LOCALE = 'en';

    public const FORMAT_MARKDOWN_MESSAGE = 'markdown_message';

    public const FORMAT_RAW_HTML = 'raw_html';

    public function isRawHtml(): bool
    {
        return $this->body_format === self::FORMAT_RAW_HTML;
    }

    public function subjectFor(?string $locale): string
    {
        return $this->valueFor('subject', $locale);
    }

    public function bodyFor(?string $locale): string
    {
        return $this->valueFor('body', $locale);
    }

    public function definition(): ?array
    {
        return EmailTemplateRegistry::get($this->key);
    }

    private function valueFor(string $field, ?string $locale): string
    {
        $locale = in_array($locale, self::SUPPORTED_LOCALES, true)
            ? $locale
            : self::FALLBACK_LOCALE;

        $value = (string) ($this->{"{$field}_{$locale}"} ?? '');

        if ($value !== '') {
            return $value;
        }

        return (string) ($this->{"{$field}_".self::FALLBACK_LOCALE} ?? '');
    }
}
