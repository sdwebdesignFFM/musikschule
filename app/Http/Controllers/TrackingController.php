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
     * Click-Tracking: Ziel-URL aus Empfänger-Token ableiten, email_clicked_at setzen.
     */
    public function click(Request $request, string $trackingId)
    {
        $recipient = CampaignRecipient::where('tracking_id', $trackingId)->first();

        if (! $recipient) {
            abort(404);
        }

        if (! $recipient->email_clicked_at) {
            $recipient->update(['email_clicked_at' => now()]);
        }

        // Ziel-URL aus dem Empfänger-Token ableiten
        $baseUrl = config('msgraph.email_base_url', config('app.url'));
        $url = rtrim($baseUrl, '/') . '/k/' . $recipient->token;

        // ?via Parameter durchreichen (für zweite E-Mail-Adresse)
        if ($request->query('via')) {
            $url .= '?via=' . $request->query('via');
        }

        return redirect($url);
    }
}
