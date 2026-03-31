<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    private const CTA_BUTTON = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 24px 0;"><tr><td style="background-color: #3D8BC9; border-radius: 8px;"><a href="{{link}}" style="display: inline-block; padding: 14px 32px; color: #FFFFFF; text-decoration: none; font-weight: bold; font-size: 15px;">Zur Rückmeldung</a></td></tr></table>';

    public function run(): void
    {
        EmailTemplate::create([
            'name' => 'Vertragsänderung Erst-Mail',
            'type' => 'initial',
            'subject' => 'Vertragsänderung – Rückmeldung erforderlich bis {{frist}}',
            'body' => '<p>{{anrede}} {{name}},</p>'
                . '<p>zum 1.8.2026 ändern sich die AGB, Schulgeldtarife und Bedingungen für Tarifermäßigungen der Musikschule Frankfurt e.&nbsp;V.</p>'
                . '<p>Um Ihnen weiterhin qualitativ hochwertigen Musikschulunterricht durch qualifizierte Lehrkräfte anbieten zu können, passen wir unsere Schulgeldtarife zum 1.8.2026 an.</p>'
                . '<p><strong>Damit Sie nach dem 1.8.2026 weiterhin Unterricht erhalten, benötigen wir Ihre aktive Zustimmung bis zum {{frist}}.</strong> Liegt uns Ihre Zustimmung bis dahin nicht vor, sind wir verpflichtet, den Unterrichtsvertrag zum 31.7.2026 zu beenden.</p>'
                . self::CTA_BUTTON
                . '<p style="color: #64748B; font-size: 13px;">Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>{{link}}</p>'
                . '<p>Mit freundlichen Grüßen<br><strong>Musikschule Frankfurt e.&nbsp;V.</strong></p>',
        ]);

        EmailTemplate::create([
            'name' => 'Vertragsänderung Erinnerung',
            'type' => 'reminder_1',
            'subject' => 'Erinnerung: Vertragsänderung – Rückmeldung erforderlich bis {{frist}}',
            'body' => '<p>{{anrede}} {{name}},</p>'
                . '<p>wir möchten Sie daran erinnern, dass sich zum <strong>1. August 2026</strong> die <strong>AGB</strong>, die <strong>Schulgeldtarife</strong> sowie die <strong>Bedingungen für Tarifermäßigungen</strong> der Musikschule Frankfurt e.&nbsp;V. ändern.</p>'
                . '<p>Damit Sie und Ihr Kind/Ihre Kinder weiterhin Unterricht erhalten können, benötigen wir dringend <strong>Ihre aktive Zustimmung</strong>. Liegt uns Ihre Zustimmung bis <strong>{{frist}}</strong> nicht vor, sind wir verpflichtet, den Unterrichtsvertrag zum <strong>31.7.2026</strong> zu beenden.</p>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 16px 0; border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; width: 100%;"><tr><td style="background-color: #F8FAFC; padding: 16px 20px;"><strong style="color: #2C4A6B;">Ihre hinterlegten Daten</strong></td></tr><tr><td style="padding: 16px 20px;"><table cellpadding="0" cellspacing="0" border="0" width="100%"><tr><td style="padding: 4px 0; color: #64748B;">Name:</td><td style="padding: 4px 0; color: #2C4A6B;">{{name}}</td></tr><tr><td style="padding: 4px 0; color: #64748B;">E-Mail:</td><td style="padding: 4px 0; color: #2C4A6B;">{{email}}</td></tr><tr><td style="padding: 4px 0; color: #64748B;">Kassenzeichen:</td><td style="padding: 4px 0; color: #2C4A6B;">{{kassenzeichen}}</td></tr></table></td></tr></table>'
                . '<p>Bitte prüfen Sie Ihre Angaben und bestätigen Sie uns über das Rückmeldeformular:</p>'
                . self::CTA_BUTTON
                . '<p style="color: #64748B; font-size: 13px;">Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>{{link}}</p>'
                . '<p>Ihre Rückmeldung wird gespeichert, und Sie erhalten im Anschluss automatisch eine Bestätigung per E-Mail.</p>'
                . '<p>Vielen Dank für Ihre Mithilfe und Ihr Vertrauen.</p>'
                . '<p>Mit freundlichen Grüßen<br><strong>Musikschule Frankfurt e.&nbsp;V.</strong></p>',
        ]);

        EmailTemplate::create([
            'name' => 'Vertragsänderung letzte Erinnerung',
            'type' => 'reminder_2',
            'subject' => 'Letzte Erinnerung: Vertragsänderung – Rückmeldung erforderlich bis {{frist}}',
            'body' => '<p>{{anrede}} {{name}},</p>'
                . '<p>dies ist unsere <strong>letzte Erinnerung</strong>: Ihre Rückmeldung zur Vertragsänderung steht noch aus.</p>'
                . '<p>Die Frist endet am <strong>{{frist}}</strong>. Ohne Ihre Zustimmung sind wir verpflichtet, den Unterrichtsvertrag zum <strong>31.7.2026</strong> zu beenden.</p>'
                . '<p>Bitte geben Sie Ihre Antwort jetzt ab:</p>'
                . self::CTA_BUTTON
                . '<p style="color: #64748B; font-size: 13px;">Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:<br>{{link}}</p>'
                . '<p>Mit freundlichen Grüßen<br><strong>Musikschule Frankfurt e.&nbsp;V.</strong></p>',
        ]);
    }
}
