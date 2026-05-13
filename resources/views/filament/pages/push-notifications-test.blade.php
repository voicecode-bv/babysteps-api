<x-filament-panels::page>
    <form wire:submit="send" class="space-y-6">
        {{ $this->sendForm }}

        <div class="flex items-center gap-3">
            <x-filament::button type="submit">Verstuur push</x-filament::button>
            @if ($lastSummary)
                <span class="text-sm text-gray-600 dark:text-gray-300">{{ $lastSummary }}</span>
            @endif
        </div>

        @if (! empty($lastResults))
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                        <tr>
                            <th class="px-3 py-2 font-medium">Token</th>
                            <th class="px-3 py-2 font-medium">Resultaat</th>
                            <th class="px-3 py-2 font-medium">Fout</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($lastResults as $row)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs break-all">{{ \Illuminate\Support\Str::limit($row['token'], 40, '…') }}</td>
                                <td class="px-3 py-2">
                                    @if ($row['success'])
                                        <span class="text-success-600 dark:text-success-400">OK</span>
                                    @else
                                        <span class="text-danger-600 dark:text-danger-400">FAIL</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300 break-all">{{ $row['error'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </form>
</x-filament-panels::page>
