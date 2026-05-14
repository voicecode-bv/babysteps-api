@php
    use App\Mail\EmailTemplates\EmailTemplateRegistry;
    use Illuminate\Mail\Markdown;
    use Illuminate\Support\Facades\App;

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

    $previewHtml = '';
    $renderError = null;

    if ($previewBody !== '') {
        try {
            $previewHtml = App::setLocale($locale, fn () => (string) app(Markdown::class)->render(
                'emails.templated',
                ['body' => $previewBody],
            ));
        } catch (\Throwable $e) {
            $renderError = $e->getMessage();
        }
    }
@endphp

<div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Live preview ({{ strtoupper($locale) }})
        </div>
        @if (! empty($samples))
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Placeholders shown with sample values.
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400">Subject</div>
            <div class="font-medium text-gray-900 dark:text-gray-100">
                {{ $previewSubject !== '' ? $previewSubject : '(empty)' }}
            </div>
        </div>

        @if ($renderError !== null)
            <div class="p-5 text-sm text-danger-600 dark:text-danger-400">
                Preview error: {{ $renderError }}
            </div>
        @elseif ($previewHtml === '')
            <div class="p-5 text-sm text-gray-400 dark:text-gray-500">
                Start typing to see a preview…
            </div>
        @else
            <iframe
                title="Email preview"
                sandbox="allow-same-origin"
                srcdoc="{{ $previewHtml }}"
                class="h-[720px] w-full border-0 bg-white"
            ></iframe>
        @endif
    </div>
</div>
