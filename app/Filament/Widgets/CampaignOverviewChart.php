<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use Filament\Widgets\ChartWidget;

class CampaignOverviewChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Kampagnen – Rücklauf';

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $campaigns = Campaign::withCount([
            'recipients',
            'recipients as accepted_count' => fn ($q) => $q->where('status', 'accepted'),
            'recipients as declined_count' => fn ($q) => $q->where('status', 'declined'),
            'recipients as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ])
            ->latest()
            ->limit(6)
            ->get()
            ->reverse();

        return [
            'datasets' => [
                [
                    'label' => 'Zugesagt',
                    'data' => $campaigns->pluck('accepted_count')->toArray(),
                    'backgroundColor' => '#22C55E',
                ],
                [
                    'label' => 'Abgesagt',
                    'data' => $campaigns->pluck('declined_count')->toArray(),
                    'backgroundColor' => '#EF4444',
                ],
                [
                    'label' => 'Ausstehend',
                    'data' => $campaigns->pluck('pending_count')->toArray(),
                    'backgroundColor' => '#D1D5DB',
                ],
            ],
            'labels' => $campaigns->pluck('name')->map(fn ($name) => mb_strlen($name) > 25 ? mb_substr($name, 0, 25) . '…' : $name)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
