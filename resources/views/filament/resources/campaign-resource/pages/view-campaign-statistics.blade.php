<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($this->getStats() as $stat)
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <div @class([
                        'rounded-lg p-2',
                        'bg-gray-100 dark:bg-gray-800' => $stat['color'] === 'gray',
                        'bg-green-100 dark:bg-green-900/30' => $stat['color'] === 'success',
                        'bg-yellow-100 dark:bg-yellow-900/30' => $stat['color'] === 'warning',
                        'bg-red-100 dark:bg-red-900/30' => $stat['color'] === 'danger',
                        'bg-blue-100 dark:bg-blue-900/30' => $stat['color'] === 'info',
                    ])>
                        <x-filament::icon
                            :icon="$stat['icon']"
                            @class([
                                'h-5 w-5',
                                'text-gray-500' => $stat['color'] === 'gray',
                                'text-green-500' => $stat['color'] === 'success',
                                'text-yellow-500' => $stat['color'] === 'warning',
                                'text-red-500' => $stat['color'] === 'danger',
                                'text-blue-500' => $stat['color'] === 'info',
                            ])
                        />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Tracking Hinweis --}}
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
        <div class="flex items-start gap-3">
            <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 text-blue-500" />
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Die Öffnungsrate ist ein Mindestwert — viele E-Mail-Clients blockieren Tracking-Pixel.
                Die tatsächliche Öffnungsrate liegt wahrscheinlich höher.
            </p>
        </div>
    </div>

    {{-- Empfänger-Tabelle --}}
    {{ $this->table }}
</x-filament-panels::page>
