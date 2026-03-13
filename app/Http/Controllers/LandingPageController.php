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
        ]);
    }

    public function show(Request $request, string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)
            ->with(['student', 'campaign.documents'])
            ->firstOrFail();

        if ($recipient->hasResponded()) {
            return view('landing.responded', compact('recipient'));
        }

        // Bestimme, über welche E-Mail-Adresse der Link geöffnet wurde
        $via = $request->query('via');
        if ($via === '2' && $recipient->student->email_2) {
            $displayEmail = $recipient->student->email_2;
        } else {
            $displayEmail = $recipient->student->email;
        }

        session(['responded_via_email_' . $token => $displayEmail]);

        return view('landing.show', compact('recipient', 'displayEmail'));
    }

    public function respond(Request $request, string $token)
    {
        $recipient = CampaignRecipient::where('token', $token)
            ->with(['student', 'campaign'])
            ->firstOrFail();

        if ($recipient->hasResponded()) {
            return view('landing.responded', compact('recipient'));
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
}
