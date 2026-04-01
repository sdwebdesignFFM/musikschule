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
        $this->recipient->loadMissing(['campaign', 'student']);
        $campaign = $this->recipient->campaign;

        // Prüfe: Kampagne noch sendbar? (Pausier-Check)
        if (! $campaign->isSendable()) {
            Log::info('SendCampaignEmail: Kampagne nicht sendbar, übersprungen', [
                'recipient_id' => $this->recipient->id,
                'campaign_status' => $campaign->status,
            ]);
            return;
        }

        // Prüfe: Empfänger bereits reagiert?
        if ($this->recipient->hasResponded()) {
            Log::info('SendCampaignEmail: Empfänger hat bereits reagiert, übersprungen', [
                'recipient_id' => $this->recipient->id,
            ]);
            return;
        }

        // Atomares Locking — verhindert parallele Verarbeitung
        if (! $this->recipient->acquireSendLock()) {
            Log::info('SendCampaignEmail: Lock nicht erworben (bereits sending/sent)', [
                'recipient_id' => $this->recipient->id,
                'email_status' => $this->recipient->email_status,
            ]);
            return;
        }

        try {
            $mailService->sendToRecipient($this->recipient, $this->campaignEmail);

            // Timestamp aktualisieren
            $timestampField = match ($this->campaignEmail->type) {
                'initial' => 'initial_sent_at',
                'reminder_1' => 'reminder_1_sent_at',
                'reminder_2' => 'reminder_2_sent_at',
            };

            $this->recipient->update([$timestampField => now()]);
            $this->recipient->markAsSent();

            Log::info('SendCampaignEmail: Erfolgreich gesendet', [
                'recipient_id' => $this->recipient->id,
                'type' => $this->campaignEmail->type,
            ]);
        } catch (\Throwable $e) {
            // Status auf failed setzen, aber Exception re-thrown für Queue-Retry
            $this->recipient->markAsFailed($e->getMessage());

            Log::error('SendCampaignEmail: Fehler beim Senden', [
                'recipient_id' => $this->recipient->id,
                'campaign_email_id' => $this->campaignEmail->id,
                'error' => $e->getMessage(),
                'attempt' => $this->recipient->send_attempts,
            ]);

            throw $e;
        }
    }

    /**
     * Job endgültig fehlgeschlagen (alle Retries aufgebraucht).
     */
    public function failed(\Throwable $exception): void
    {
        $this->recipient->markAsFailed($exception->getMessage());

        Log::error('SendCampaignEmail endgültig fehlgeschlagen', [
            'recipient_id' => $this->recipient->id,
            'campaign_email_id' => $this->campaignEmail->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
