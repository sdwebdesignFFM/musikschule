<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignDocument;
use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\Student;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        $templates = EmailTemplate::all()->keyBy('type');
        $students = Student::all();

        // Hauptkampagne: Vertragsänderung 2026 – als Entwurf (bereit zum Starten)
        $campaign = Campaign::create([
            'name' => 'Vertragsänderung ab 1.8.2026 – Rückmeldung erforderlich',
            'subtitle' => 'Vertragsänderungen ab 1.8.2026',
            'description' => '<p><strong>Neue AGB – Schulgeldtarife – Bedingungen für Tarifermäßigungen</strong></p>'
                . '<p><strong>Aktive Zustimmung des Zahlungspflichtigen erforderlich</strong></p>'
                . '<p>Zum 1.8.2026 ändern sich die AGB, Schulgeldtarife und Bedingungen für Tarifermäßigungen der Musikschule Frankfurt e.&nbsp;V.</p>'
                . '<p>Um Ihnen weiterhin qualitativ hochwertigen Musikschulunterricht durch qualifizierte Lehrkräfte anbieten zu können, passen wir unsere Schulgeldtarife zum 1.8.2026 an.</p>'
                . '<p><strong>Damit Sie nach dem 1.8.2026 weiterhin Unterricht erhalten, benötigen wir Ihre aktive Zustimmung innerhalb von 4 Wochen nach Erhalt dieser Mail.</strong> Liegt uns Ihre Zustimmung bis dahin nicht vor, sind wir verpflichtet, den Unterrichtsvertrag zum 31.7.2026 zu beenden.</p>',
            'document_section_title' => 'Zugehörige Dokumente',
            'checkbox_text' => 'Ich bestätige, dass ich die/der Zahlungspflichtige bin. Ich habe die Daten geprüft und die Informationen zur Kenntnis genommen.',
            'accept_text' => 'Ich stimme den Vertragsänderungen zu',
            'decline_text' => 'Nein, ich stimme nicht zu. Mein Unterrichtsvertrag endet zum 31.7.2026',
            'status' => 'draft',
            'start_date' => now()->format('Y-m-d'),
            'deadline' => now()->addWeeks(4)->format('Y-m-d'),
        ]);

        // Dokumente anhängen (echte PDFs)
        CampaignDocument::create([
            'campaign_id' => $campaign->id,
            'link_text' => 'Allgemeine Geschäftsbedingungen',
            'file_path' => 'campaign-documents/agb-2026.pdf',
            'sort_order' => 1,
        ]);

        CampaignDocument::create([
            'campaign_id' => $campaign->id,
            'link_text' => 'Schulgeldtarife',
            'file_path' => 'campaign-documents/schulgeldtarife-2026.pdf',
            'sort_order' => 2,
        ]);

        CampaignDocument::create([
            'campaign_id' => $campaign->id,
            'link_text' => 'Bedingungen für Tarifermäßigungen',
            'file_path' => 'campaign-documents/tarifermässigungen-2026.pdf',
            'sort_order' => 3,
        ]);

        // E-Mails aus Templates
        CampaignEmail::create([
            'campaign_id' => $campaign->id,
            'type' => 'initial',
            'subject' => $templates['initial']->subject,
            'body' => $templates['initial']->body,
            'delay_days' => 0,
            'template_id' => $templates['initial']->id,
        ]);

        CampaignEmail::create([
            'campaign_id' => $campaign->id,
            'type' => 'reminder_1',
            'subject' => $templates['reminder_1']->subject,
            'body' => $templates['reminder_1']->body,
            'delay_days' => 7,
            'template_id' => $templates['reminder_1']->id,
        ]);

        CampaignEmail::create([
            'campaign_id' => $campaign->id,
            'type' => 'reminder_2',
            'subject' => $templates['reminder_2']->subject,
            'body' => $templates['reminder_2']->body,
            'delay_days' => 14,
            'template_id' => $templates['reminder_2']->id,
        ]);

        // Alle aktiven Schüler als Empfänger
        foreach ($students as $student) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'student_id' => $student->id,
            ]);
        }
    }
}
