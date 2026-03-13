<?php

namespace App\Jobs;

use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use App\Services\CampaignMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 120, 300];

    public function __construct(
        public CampaignRecipient $recipient,
        public CampaignEmail $campaignEmail,
    ) {}

    public function handle(CampaignMailService $mailService): void
    {
        // Nicht senden wenn Empfänger bereits reagiert hat
        if ($this->recipient->hasResponded()) {
            Log::info('SendCampaignEmail: Empfänger hat bereits reagiert, übersprungen', [
                'recipient_id' => $this->recipient->id,
            ]);
            return;
        }

        $mailService->sendToRecipient($this->recipient, $this->campaignEmail);

        // Timestamp aktualisieren
        $timestampField = match ($this->campaignEmail->type) {
            'initial' => 'initial_sent_at',
            'reminder_1' => 'reminder_1_sent_at',
            'reminder_2' => 'reminder_2_sent_at',
        };

        $this->recipient->update([$timestampField => now()]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCampaignEmail fehlgeschlagen', [
            'recipient_id' => $this->recipient->id,
            'campaign_email_id' => $this->campaignEmail->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
