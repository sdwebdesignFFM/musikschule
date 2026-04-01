<?php

namespace App\Services;

use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use Illuminate\Support\Facades\Log;

class CampaignMailService
{
    public function __construct(
        private Office365MailService $mailService,
        private PlaceholderService $placeholderService,
    ) {}

    /**
     * Sende eine Kampagnen-E-Mail an einen Empfänger (beide Adressen).
     * Duplikat-Schutz: Prüft email_1_sent / email_2_sent Flags pro Adresse.
     */
    public function sendToRecipient(CampaignRecipient $recipient, CampaignEmail $email): void
    {
        $recipient->loadMissing(['student', 'campaign']);

        $subject = $this->placeholderService->replace($email->subject, $recipient);
        $sentAny = false;

        // Sende an primäre E-Mail-Adresse (nur wenn noch nicht gesendet)
        if (! $recipient->email_1_sent) {
            $body = $this->placeholderService->replace($email->body, $recipient);
            $htmlBody = $this->wrapInHtmlTemplate($body);
            $htmlBody = $this->injectTracking($htmlBody, $recipient->tracking_id);

            $this->mailService->sendMail(
                to: $recipient->student->email,
                subject: $subject,
                htmlBody: $htmlBody,
            );

            $recipient->update(['email_1_sent' => true]);
            $sentAny = true;
        }

        // Sende an zweite E-Mail-Adresse (nur wenn vorhanden und noch nicht gesendet)
        if ($recipient->student->email_2 && ! $recipient->email_2_sent) {
            $bodyEmail2 = $this->placeholderService->replace($email->body, $recipient, viaEmail: 2);
            $htmlBodyEmail2 = $this->wrapInHtmlTemplate($bodyEmail2);
            $htmlBodyEmail2 = $this->injectTracking($htmlBodyEmail2, $recipient->tracking_id);

            $this->mailService->sendMail(
                to: $recipient->student->email_2,
                subject: $subject,
                htmlBody: $htmlBodyEmail2,
            );

            $recipient->update(['email_2_sent' => true]);
            $sentAny = true;
        }

        if ($sentAny) {
            Log::info('Kampagnen-Mail gesendet', [
                'campaign_id' => $recipient->campaign_id,
                'student_id' => $recipient->student_id,
                'type' => $email->type,
                'email_1_sent' => $recipient->email_1_sent,
                'email_2_sent' => $recipient->email_2_sent,
            ]);
        }
    }

    /**
     * Sende Bestätigungs-E-Mail nach Antwort (an beide Adressen).
     */
    public function sendConfirmation(CampaignRecipient $recipient): void
    {
        $recipient->loadMissing(['student', 'campaign']);

        $student = $recipient->student;
        $campaign = $recipient->campaign;
        $status = $recipient->status === 'accepted' ? 'Zugestimmt' : 'Nicht zugestimmt';
        $statusColor = $recipient->status === 'accepted' ? '#16A34A' : '#DC2626';

        $subject = "Bestätigung Ihrer Rückmeldung – {$campaign->name}";
        $body = '<p>Guten Tag ' . e($student->name) . ',</p>'
            . '<p>vielen Dank für Ihre Rückmeldung. Wir bestätigen hiermit den Eingang Ihrer Entscheidung zur Vertragsänderung.</p>'
            . '<table cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; width: 100%;">'
            . '<tr><td style="background-color: #F8FAFC; padding: 16px 20px; border-bottom: 1px solid #E2E8F0;">'
            . '<strong style="color: #2C4A6B;">Zusammenfassung</strong></td></tr>'
            . '<tr><td style="padding: 16px 20px;">'
            . '<table cellpadding="0" cellspacing="0" border="0" width="100%">'
            . '<tr><td style="padding: 6px 0; color: #64748B;">Status</td>'
            . '<td style="padding: 6px 0; text-align: right;"><strong style="color: ' . $statusColor . ';">' . $status . '</strong></td></tr>'
            . '<tr><td style="padding: 6px 0; color: #64748B;">Eingereicht am</td>'
            . '<td style="padding: 6px 0; text-align: right; color: #2C4A6B;">' . $recipient->responded_at->format('d.m.Y, H:i') . ' Uhr</td></tr>'
            . '<tr><td style="padding: 6px 0; color: #64748B;">Kassenzeichen</td>'
            . '<td style="padding: 6px 0; text-align: right; color: #2C4A6B;">' . e($student->customer_number) . '</td></tr>'
            . '</table></td></tr></table>'
            . '<p style="color: #64748B; font-size: 13px;">Diese E-Mail dient als Nachweis Ihrer Rückmeldung.</p>'
            . '<p>Mit freundlichen Grüßen<br><strong>Musikschule Frankfurt e.&nbsp;V.</strong></p>';

        $htmlBody = $this->wrapInHtmlTemplate($body);

        $this->mailService->sendMail(
            to: $student->email,
            subject: $subject,
            htmlBody: $htmlBody,
        );

        if ($student->email_2) {
            $this->mailService->sendMail(
                to: $student->email_2,
                subject: $subject,
                htmlBody: $htmlBody,
            );
        }
    }

    /**
     * Open-Tracking-Pixel vor </body> einfügen.
     */
    private function injectTracking(string $html, string $trackingId): string
    {
        $baseUrl = config('msgraph.email_base_url', config('app.url'));
        $pixelUrl = rtrim($baseUrl, '/') . '/t/open/' . $trackingId;
        $pixel = '<tr><td style="font-size:0;line-height:0;height:0;overflow:hidden;mso-hide:all;" aria-hidden="true"><img src="' . $pixelUrl . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" /></td></tr>';

        // Vor dem letzten </table> in der äußeren Struktur einfügen
        $pos = strrpos($html, '</table>');
        if ($pos !== false) {
            $html = substr_replace($html, $pixel, $pos, 0);
        }

        return $html;
    }

    private function wrapInHtmlTemplate(string $body): string
    {
        $logoUrl = config('msgraph.email_base_url', config('app.url')) . '/images/logo.jpg';
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Musikschule Frankfurt</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F1F5F9; font-family: 'Century Gothic', 'CenturyGothic', Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F1F5F9;">
        <tr>
            <td align="center" style="padding: 30px 15px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td align="center" style="padding: 0 0 24px 0;">
                            <div style="background-color: #FFFFFF; border-radius: 12px; display: inline-block; padding: 16px 24px;">
                                <img src="{$logoUrl}" alt="Musikschule Frankfurt" width="220" style="display: block; max-width: 220px; height: auto;">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #FFFFFF; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td style="background-color: #3D8BC9; height: 4px; border-radius: 12px 12px 0 0; font-size: 0; line-height: 0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding: 36px 40px; color: #2C4A6B; font-size: 15px; line-height: 1.7; font-family: 'Century Gothic', 'CenturyGothic', Arial, Helvetica, sans-serif;">
                                        {$body}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 28px 20px 0 20px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="font-size: 13px; color: #64748B; line-height: 1.6; font-family: 'Century Gothic', 'CenturyGothic', Arial, Helvetica, sans-serif;">
                                        <strong>Musikschule Frankfurt e.&thinsp;V.</strong><br>
                                        Berliner Straße 51, 60311 Frankfurt
                                    </td>
                                </tr>
                                <tr>
                                    <td style="height: 20px; font-size: 0; line-height: 0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 16px; border-top: 1px solid #E2E8F0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding: 12px 0;">
                                                    <a href="https://www.musikschule-frankfurt.de/impressum" style="color: #3D8BC9; text-decoration: none; font-size: 12px;">Impressum</a>
                                                    <span style="color: #CBD5E1; padding: 0 8px;">|</span>
                                                    <a href="https://www.musikschule-frankfurt.de/datenschutz" style="color: #3D8BC9; text-decoration: none; font-size: 12px;">Datenschutz</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 4px 0 8px 0; font-size: 11px; color: #94A3B8;">
                                        Vereinsregister: AG Frankfurt am Main · VR 5258 · Vorsitzende: Sylvia Weber
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 0 0 8px 0; font-size: 11px; color: #94A3B8;">
                                        &copy; {$year} Musikschule Frankfurt e.&thinsp;V. · Alle Rechte vorbehalten
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
