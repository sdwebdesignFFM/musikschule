<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /**
     * Open-Tracking: 1x1 transparentes GIF zurückgeben, email_opened_at setzen.
     */
    public function open(string $trackingId): Response
    {
        $recipient = CampaignRecipient::where('tracking_id', $trackingId)->first();

        if ($recipient && ! $recipient->email_opened_at) {
            $recipient->update(['email_opened_at' => now()]);
        }

        // 1x1 transparentes GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($gif),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Click-Tracking: Redirect zur Ziel-URL, email_clicked_at setzen.
     */
    public function click(Request $request, string $trackingId)
    {
        $url = $request->query('url');

        // Sicherheits-Check: Nur eigene Domain als Redirect-Ziel
        if (! $this->isAllowedRedirect($url)) {
            abort(400, 'Ungültige Redirect-URL');
        }

        $recipient = CampaignRecipient::where('tracking_id', $trackingId)->first();

        if ($recipient && ! $recipient->email_clicked_at) {
            $recipient->update(['email_clicked_at' => now()]);
        }

        return redirect($url);
    }

    private function isAllowedRedirect(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            // Relative URLs (z.B. /k/token) sind erlaubt, aber keine protocol-relative URLs (//)
            return str_starts_with($url, '/') && ! str_starts_with($url, '//');
        }

        $allowedHosts = [
            parse_url(config('app.url'), PHP_URL_HOST),
            parse_url(config('msgraph.email_base_url', ''), PHP_URL_HOST),
        ];

        return in_array($parsed['host'], array_filter($allowedHosts));
    }
}
