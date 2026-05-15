<?php

namespace App\Http\Controllers;

use App\Jobs\SendConfirmationEmail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Student;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function preview(Campaign $campaign)
    {
        $existingRecipient = $campaign->recipients()->with('student')->first();

        if ($existingRecipient) {
            $recipient = $existingRecipient;
        } else {
            $student = Student::first() ?? new Student([
                'name' => 'Max Mustermann',
                'email' => 'max@beispiel.de',
                'email_2' => 'maria@beispiel.de',
                'customer_number' => 'MS-0000',
            ]);

            $recipient = new CampaignRecipient([
                'campaign_id' => $campaign->id,
                'student_id' => $student->id ?? 0,
                'token' => 'preview',
                'status' => 'pending',
            ]);
            $recipient->setRelation('student', $student);
            $recipient->setRelation('campaign', $campaign->load('documents'));
        }

        return view('landing.show', [
            'recipient' => $recipient,
            'isPreview' => true,
            'displayEmail' => $existingRecipient ? $existingRecipient->student->email : ($student->email ?? 'max@beispiel.de'),
            ...$this->campaignPlaceholders($campaign),
        ]);
    }

    public function show(Request $request, string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)
            ->with(['student'])
            ->firstOrFail();

        $campaign = Campaign::withTrashed()->with('documents')->find($recipient->campaign_id);
        $recipient->setRelation('campaign', $campaign);

        if ($accidental = $this->accidentalResendView($recipient, $campaign)) {
            return $accidental;
        }

        if ($recipient->hasResponded()) {
            return view('landing.responded', compact('recipient'));
        }

        if ($campaign->isExpired()) {
            return view('landing.expired', compact('recipient'));
        }

        // Bestimme, über welche E-Mail-Adresse der Link geöffnet wurde
        $via = $request->query('via');
        if ($via === '2' && $recipient->student->email_2) {
            $displayEmail = $recipient->student->email_2;
        } else {
            $displayEmail = $recipient->student->email;
        }

        session(['responded_via_email_' . $token => $displayEmail]);

        return view('landing.show', [
            'recipient' => $recipient,
            'displayEmail' => $displayEmail,
            ...$this->campaignPlaceholders($recipient->campaign),
        ]);
    }

    public function respond(Request $request, string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)
            ->with(['student'])
            ->firstOrFail();

        $campaign = Campaign::withTrashed()->find($recipient->campaign_id);
        $recipient->setRelation('campaign', $campaign);

        if ($accidental = $this->accidentalResendView($recipient, $campaign)) {
            return $accidental;
        }

        if ($recipient->hasResponded()) {
            return view('landing.responded', compact('recipient'));
        }

        if ($campaign->isExpired()) {
            return view('landing.expired', compact('recipient'));
        }

        $request->validate([
            'response' => 'required|in:accepted,declined',
        ]);

        $respondedViaEmail = session('responded_via_email_' . $token, $recipient->student->email);

        $recipient->update([
            'status' => $request->input('response'),
            'responded_at' => now(),
            'ip_address' => $request->ip(),
            'responded_via_email' => $respondedViaEmail,
        ]);

        SendConfirmationEmail::dispatch($recipient);

        return view('landing.responded', compact('recipient'));
    }

    /**
     * Wenn der Token zu einer soft-deleted (oder fehlenden) Kampagne gehört, wurde diese
     * E-Mail versehentlich versendet. Liefert die passende Hinweisseite zurück — oder null,
     * wenn die Kampagne noch existiert und der normale Flow weiterlaufen darf.
     */
    private function accidentalResendView(CampaignRecipient $recipient, ?Campaign $campaign)
    {
        if ($campaign && ! $campaign->trashed()) {
            return null;
        }

        $previous = CampaignRecipient::where('student_id', $recipient->student_id)
            ->where('id', '!=', $recipient->id)
            ->whereIn('status', ['accepted', 'declined'])
            ->whereHas('campaign')
            ->with(['campaign', 'student'])
            ->orderByDesc('responded_at')
            ->first();

        if ($previous) {
            return view('landing.responded', [
                'recipient' => $previous,
                'accidentalResend' => true,
            ]);
        }

        return view('landing.accidental', compact('recipient'));
    }

    private function campaignPlaceholders(Campaign $campaign): array
    {
        $deadline = $campaign->deadline?->format('d.m.Y') ?? '';
        $replace = fn (?string $text) => str_replace(
            ['{{frist}}', '{{deadline}}'],
            $deadline,
            $text ?? '',
        );

        return [
            'campaignName' => $replace($campaign->name),
            'campaignSubtitle' => $replace($campaign->subtitle),
            'campaignDescription' => $replace($campaign->description),
        ];
    }
}
