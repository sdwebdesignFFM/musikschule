<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignEmail;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendCampaignReminders extends Command
{
    protected $signature = 'campaigns:send-reminders';
    protected $description = 'Erinnerungen an Empfänger ohne Reaktion senden (basierend auf delay_days)';

    public function handle(): int
    {
        $activeCampaigns = Campaign::where('status', 'active')->with('emails')->get();

        $sentCount = 0;

        foreach ($activeCampaigns as $campaign) {
            foreach (['reminder_1', 'reminder_2'] as $type) {
                $email = $campaign->emails->firstWhere('type', $type);
                if (!$email) continue;

                $sentAtField = "{$type}_sent_at";
                $previousField = $type === 'reminder_1' ? 'initial_sent_at' : 'reminder_1_sent_at';

                // Empfänger die: pending sind, vorherige Mail erhalten haben,
                // diese Erinnerung noch nicht erhalten haben, email_status = 'sent'
                $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'pending')
                    ->where('email_status', 'sent')
                    ->whereNotNull($previousField)
                    ->whereNull($sentAtField)
                    ->get();

                foreach ($recipients as $recipient) {
                    // Prüfen ob delay_days seit der Erst-Mail erreicht sind
                    $initialSentAt = $recipient->initial_sent_at;
                    if (!$initialSentAt) continue;

                    $dueDate = $initialSentAt->copy()->addDays($email->delay_days);
                    if (now()->lt($dueDate)) continue;

                    // Atomares Reset: Nur wenn noch im Status 'sent', damit parallele Cron-Runs keine Duplikate erzeugen
                    $affected = DB::table('campaign_recipients')
                        ->where('id', $recipient->id)
                        ->where('email_status', 'sent')
                        ->update([
                            'email_status' => 'pending',
                            'email_1_sent' => false,
                            'email_2_sent' => false,
                            'updated_at' => now(),
                        ]);

                    if ($affected > 0) {
                        SendCampaignEmail::dispatch($recipient, $email);
                        $sentCount++;
                    }
                }
            }
        }

        $this->info("Erinnerungen dispatched: {$sentCount}");
        return Command::SUCCESS;
    }
}
