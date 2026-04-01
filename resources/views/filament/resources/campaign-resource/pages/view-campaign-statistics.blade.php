<x-filament-panels::page>
    {{-- Stats Cards --}}
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem;">
        @foreach ($this->getStats() as $stat)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem;">
                    <div @class([
                        'flex items-center justify-center rounded-lg',
                        'bg-gray-50 dark:bg-gray-800' => $stat['color'] === 'gray',
                        'bg-green-50 dark:bg-green-900/20' => $stat['color'] === 'success',
                        'bg-yellow-50 dark:bg-yellow-900/20' => $stat['color'] === 'warning',
                        'bg-red-50 dark:bg-red-900/20' => $stat['color'] === 'danger',
                        'bg-blue-50 dark:bg-blue-900/20' => $stat['color'] === 'info',
                    ]) style="padding: 0.5rem;">
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
                    <div>
                        <p style="font-size: 0.75rem; font-weight: 500; color: #6b7280;">{{ $stat['label'] }}</p>
                        <p style="font-size: 1.5rem; font-weight: 600;" @class([
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
    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; border-radius: 0.5rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
        <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4" style="flex-shrink: 0;" />
        Öffnungsrate ist ein Mindestwert — viele E-Mail-Clients blockieren Tracking-Pixel.
    </div>

    {{-- Empfänger-Tabelle --}}
    {{ $this->table }}
</x-filament-panels::page>
