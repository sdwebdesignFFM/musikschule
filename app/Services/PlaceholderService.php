<?php

namespace App\Services;

use App\Models\CampaignRecipient;

class PlaceholderService
{
    public function replace(string $text, CampaignRecipient $recipient, ?int $viaEmail = null): string
    {
        $student = $recipient->student;
        $campaign = $recipient->campaign;

        $frist = $campaign->deadline->format('d.m.Y');

        // Click-Tracking: Kurzer Redirect-Link (Ziel-URL wird serverseitig aus Token aufgelöst)
        $baseUrl = config('msgraph.email_base_url') ?: config('app.url');
        $link = rtrim($baseUrl, '/') . '/t/click/' . $recipient->tracking_id;
        if ($viaEmail) {
            $link .= '?via=' . $viaEmail;
        }

        $placeholders = [
            '{{anrede}}' => $student->salutation,
            '{{name}}' => $student->name,
            '{{email}}' => $student->email,
            '{{email_2}}' => $student->email_2 ?? '',
            '{{kassenzeichen}}' => $student->customer_number,
            '{{kundennummer}}' => $student->customer_number,
            '{{link}}' => $link,
            '{{deadline}}' => $campaign->deadline->format('d.m.Y'),
            '{{frist}}' => $frist,
            '{{kampagne}}' => $campaign->name,
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $text
        );
    }
}
