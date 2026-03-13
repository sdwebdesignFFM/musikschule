<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeCampaigns = Campaign::where('status', 'active')->count();
        $totalRecipients = CampaignRecipient::count();
        $respondedRecipients = CampaignRecipient::where('status', '!=', 'pending')->count();
        $responseRate = $totalRecipients > 0
            ? round(($respondedRecipients / $totalRecipients) * 100, 1)
            : 0;
        $sentEmails = CampaignRecipient::whereNotNull('initial_sent_at')->count()
            + CampaignRecipient::whereNotNull('reminder_1_sent_at')->count()
            + CampaignRecipient::whereNotNull('reminder_2_sent_at')->count();

        return [
            Stat::make('Aktive Kampagnen', $activeCampaigns)
                ->description('Aktuell laufend')
                ->icon('heroicon-o-megaphone')
                ->color('primary'),
            Stat::make('Gesamt-Empfänger', $totalRecipients)
                ->description('Über alle Kampagnen')
                ->icon('heroicon-o-users')
                ->color('gray'),
            Stat::make('Rücklaufquote', $responseRate . '%')
                ->description($respondedRecipients . ' von ' . $totalRecipients . ' beantwortet')
                ->icon('heroicon-o-chart-bar')
                ->color($responseRate >= 70 ? 'success' : ($responseRate >= 40 ? 'warning' : 'danger')),
            Stat::make('Versendete E-Mails', $sentEmails)
                ->description('Erst-Mails + Erinnerungen')
                ->icon('heroicon-o-envelope')
                ->color('primary'),
        ];
    }
}
