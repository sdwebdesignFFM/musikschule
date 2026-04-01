<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($this->getStats() as $stat)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3 p-4">
                    <div @class([
                        'flex items-center justify-center rounded-lg p-2',
                        'bg-gray-50 dark:bg-gray-800' => $stat['color'] === 'gray',
                        'bg-green-50 dark:bg-green-900/20' => $stat['color'] === 'success',
                        'bg-yellow-50 dark:bg-yellow-900/20' => $stat['color'] === 'warning',
                        'bg-red-50 dark:bg-red-900/20' => $stat['color'] === 'danger',
                        'bg-blue-50 dark:bg-blue-900/20' => $stat['color'] === 'info',
                    ])>
                        <x-filament::icon
                            :icon="$stat['icon']"
                            @class([
                                'h-5 w-5',
                                'text-gray-400 dark:text-gray-500' => $stat['color'] === 'gray',
                                'text-green-500 dark:text-green-400' => $stat['color'] === 'success',
                                'text-yellow-500 dark:text-yellow-400' => $stat['color'] === 'warning',
                                'text-red-500 dark:text-red-400' => $stat['color'] === 'danger',
                                'text-blue-500 dark:text-blue-400' => $stat['color'] === 'info',
                            ])
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p @class([
                            'text-2xl font-semibold',
                            'text-gray-900 dark:text-white' => $stat['color'] === 'gray',
                            'text-green-600 dark:text-green-400' => $stat['color'] === 'success',
                            'text-yellow-600 dark:text-yellow-400' => $stat['color'] === 'warning',
                            'text-red-600 dark:text-red-400' => $stat['color'] === 'danger',
                            'text-blue-600 dark:text-blue-400' => $stat['color'] === 'info',
                        ])>{{ $stat['value'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tracking Hinweis --}}
    <div class="flex items-center gap-2 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700 ring-1 ring-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:ring-blue-800">
        <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 shrink-0" />
        Öffnungsrate ist ein Mindestwert — viele E-Mail-Clients blockieren Tracking-Pixel.
    </div>

    {{-- Empfänger-Tabelle --}}
    {{ $this->table }}
</x-filament-panels::page>
