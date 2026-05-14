@php
    /** @var array<string, string> $placeholders */
@endphp

@if (! empty($placeholders))
    <div class="space-y-2">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Available placeholders for this template. Use them as
            <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-800">{name}</code>
            inside the subject or body.
        </p>
        <ul class="space-y-1">
            @foreach ($placeholders as $name => $description)
                <li class="flex items-baseline gap-2 text-sm">
                    <code class="rounded bg-primary-50 px-2 py-0.5 font-mono text-xs text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ '{'.$name.'}' }}
                    </code>
                    <span class="text-gray-600 dark:text-gray-400">{{ $description }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
