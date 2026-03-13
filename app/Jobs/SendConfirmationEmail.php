<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Services\CampaignMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 120, 300];

    public function __construct(
        public CampaignRecipient $recipient,
    ) {}

    public function handle(CampaignMailService $mailService): void
    {
        $mailService->sendConfirmation($this->recipient);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendConfirmationEmail fehlgeschlagen', [
            'recipient_id' => $this->recipient->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
