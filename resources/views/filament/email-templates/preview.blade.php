@php
    use App\Mail\EmailTemplates\EmailTemplateRegistry;
    use Illuminate\Support\Str;

    /** @var string $subject */
    /** @var string $body */
    /** @var string $locale */

    $record = $this->getRecord();
    $samples = $record
        ? (EmailTemplateRegistry::get($record->key)['samples'] ?? [])
        : [];

    $tokens = [];
    foreach ($samples as $name => $value) {
        $tokens['{'.$name.'}'] = (string) $value;
    }

    $previewSubject = $tokens === [] ? $subject : strtr($subject, $tokens);
    $previewBody = $tokens === [] ? $body : strtr($body, $tokens);

    try {
        $previewBodyHtml = $previewBody === '' ? '' : Str::of($previewBody)->markdown()->sanitizeHtml()->toString();
    } catch (\Throwable $e) {
        $previewBodyHtml = '<p class="text-danger-600">'.e($e->getMessage()).'</p>';
    }
@endphp

<div class="space-y-3">
    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Live preview ({{ strtoupper($locale) }})
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Subject</div>
            <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ $previewSubject !== '' ? $previewSubject : '(empty)' }}
            </div>
        </div>
        <div class="prose prose-sm max-w-none p-5 dark:prose-invert">
            @if ($previewBodyHtml === '')
                <p class="text-gray-400 dark:text-gray-500">Start typing to see a preview…</p>
            @else
                {!! $previewBodyHtml !!}
            @endif
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Placeholders are replaced with sample values in this preview.
    </p>
</div>
