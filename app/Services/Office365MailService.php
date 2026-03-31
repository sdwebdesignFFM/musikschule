<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Office365MailService
{
    private ?string $accessToken = null;

    public function sendMail(string $to, string $subject, string $htmlBody): void
    {
        // Lokal: über Laravel Mail (Mailpit) senden, außer Graph API ist erzwungen
        if (app()->environment('local', 'testing') && !config('msgraph.force_live')) {
            $this->sendViaLaravelMail($to, $subject, $htmlBody);
            return;
        }

        $this->throttle();

        $token = $this->getAccessToken();
        $sender = config('msgraph.sender_email');

        $client = new Client();

        $response = $client->post(
            "https://graph.microsoft.com/v1.0/users/{$sender}/sendMail",
            [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => [
                        'subject' => $subject,
                        'body' => [
                            'contentType' => 'HTML',
                            'content' => $htmlBody,
                        ],
                        'from' => [
                            'emailAddress' => [
                                'name' => config('app.name', 'Musikschule Frankfurt'),
                                'address' => $sender,
                            ],
                        ],
                        'toRecipients' => [
                            [
                                'emailAddress' => [
                                    'address' => $to,
                                ],
                            ],
                        ],
                        'internetMessageHeaders' => [
                            [
                                'name' => 'X-Mailer',
                                'value' => 'Musikschule Frankfurt Kampagnen',
                            ],
                        ],
                    ],
                    'saveToSentItems' => true,
                ],
            ]
        );

        Log::channel('stack')->info('Office365: E-Mail gesendet', [
            'to' => $to,
            'subject' => $subject,
            'status' => $response->getStatusCode(),
        ]);
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $cached = Cache::get('msgraph_access_token');
        if ($cached) {
            $this->accessToken = $cached;
            return $cached;
        }

        $tenantId = config('msgraph.tenant_id');
        $clientId = config('msgraph.client_id');
        $clientSecret = config('msgraph.client_secret');

        $client = new Client();
        $response = $client->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'form_params' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);
        $token = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? 3600;

        // Cache Token mit 5 Minuten Puffer vor Ablauf
        Cache::put('msgraph_access_token', $token, $expiresIn - 300);

        $this->accessToken = $token;
        return $token;
    }

    private function sendViaLaravelMail(string $to, string $subject, string $htmlBody): void
    {
        Mail::html($htmlBody, function ($message) use ($to, $subject) {
            $message->to($to)
                ->subject($subject)
                ->from(
                    config('msgraph.sender_email', config('mail.from.address')),
                    config('app.name')
                );
        });

        Log::info('Mailpit: E-Mail gesendet (lokal)', [
            'to' => $to,
            'subject' => $subject,
        ]);
    }

    /**
     * Rate Limiting: Max 30 Mails pro Minute (Office 365 Grenze).
     * Wartet falls das Limit erreicht ist.
     */
    private function throttle(): void
    {
        $limit = config('msgraph.rate_limit_per_minute', 30);
        $key = 'msgraph_mail_count';
        $windowKey = 'msgraph_mail_window_start';

        $windowStart = Cache::get($windowKey);
        $count = Cache::get($key, 0);

        if ($windowStart && now()->diffInSeconds($windowStart) < 60) {
            if ($count >= $limit) {
                $waitSeconds = 60 - now()->diffInSeconds($windowStart);
                Log::info("Office365: Rate Limit erreicht, warte {$waitSeconds}s");
                sleep($waitSeconds + 1);
                Cache::put($key, 0, 120);
                Cache::put($windowKey, now(), 120);
            }
        } else {
            Cache::put($key, 0, 120);
            Cache::put($windowKey, now(), 120);
        }

        Cache::increment($key);
    }
}
