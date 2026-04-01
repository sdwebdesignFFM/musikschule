<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use Illuminate\Console\Command;

class CompleteCampaigns extends Command
{
    protected $signature = 'campaigns:complete';
    protected $description = 'Kampagnen abschließen, deren Deadline erreicht ist';

    public function handle(): int
    {
        // Auch pausierte Kampagnen nach Deadline abschließen
        $completed = Campaign::whereIn('status', ['active', 'paused'])
            ->where('deadline', '<', now()->startOfDay())
            ->get();

        foreach ($completed as $campaign) {
            $campaign->update(['status' => 'completed']);
            $this->info("Kampagne abgeschlossen: {$campaign->name}");
        }

        $this->info("Abgeschlossen: {$completed->count()} Kampagne(n)");
        return Command::SUCCESS;
    }
}
