<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::updateOrCreate(['slug' => 'impressum'], [
            'title' => 'Impressum',
            'content' => <<<'HTML'
<h3>Musikschule Frankfurt e.&thinsp;V.</h3>
<p>Berliner Straße 51<br>60311 Frankfurt am Main<br>E-Mail: webmaster@musikschule-frankfurt.eu</p>
<h4>Vertretungsberechtigte</h4>
<p>Vorsitzende: Sylvia Weber</p>
<h4>Registereintrag</h4>
<p>Amtsgericht Frankfurt am Main<br>Vereinsregisternummer: VR5258<br>Gemeinnütziger eingetragener Verein</p>
<h4>Haftungsausschluss</h4>
<p>Die Musikschule Frankfurt e.&thinsp;V. übernimmt keinerlei Gewähr für die Aktualität, Korrektheit, Vollständigkeit oder Qualität der bereitgestellten Informationen. Haftungsansprüche, welche sich auf Schäden materieller oder ideeller Art beziehen, die durch die Nutzung oder Nichtnutzung der dargebotenen Informationen bzw. durch die Nutzung fehlerhafter und unvollständiger Informationen verursacht wurden, sind grundsätzlich ausgeschlossen.</p>
<h4>Externe Links</h4>
<p>Die Musikschule Frankfurt e.&thinsp;V. distanziert sich hiermit ausdrücklich von allen Inhalten aller verlinkten bzw. verknüpften Seiten, die nach der Linksetzung verändert wurden. Für illegale, fehlerhafte oder unvollständige Inhalte und insbesondere für Schäden, die aus der Nutzung oder Nichtnutzung solcherart dargebotener Informationen entstehen, haftet allein der Anbieter der Seite, auf welche verwiesen wurde.</p>
<h4>Datenschutz</h4>
<p>Die Nutzung der im Rahmen des Impressums oder vergleichbarer Angaben veröffentlichten Kontaktdaten wie Postanschriften, Telefon- und Faxnummern sowie E-Mail-Adressen durch Dritte zur Übersendung von nicht ausdrücklich angeforderten Informationen ist nicht gestattet.</p>
<p><em>Design: desayuno.de | Fotografie: Hanna Rudolf, istockphoto | Programmierung: moon-media.biz</em></p>
HTML,
        ]);

        Page::updateOrCreate(['slug' => 'datenschutz'], [
            'title' => 'Datenschutzerklärung',
            'content' => <<<'HTML'
<h3>Datenschutzerklärung</h3>
<h4>Geltungsbereich</h4>
<p>Diese Datenschutzerklärung soll die Nutzer dieser Website gemäß Bundesdatenschutzgesetz und Telemediengesetz über die Art, den Umfang und den Zweck der Erhebung und Verwendung personenbezogener Daten durch die Musikschule Frankfurt e.&thinsp;V. informieren.</p>
<h4>Zugriffsdaten / Server-Logfiles</h4>
<p>Der Webserver erhebt und speichert automatisch Informationen in sogenannten Server-Logfiles, die Ihr Browser automatisch übermittelt. Dies sind: Browsertyp und -version, verwendetes Betriebssystem, Referrer-URL, Hostname des zugreifenden Rechners, Uhrzeit der Serveranfrage und die IP-Adresse. Diese Daten werden ausschließlich zu statistischen Zwecken und zur Verbesserung des Angebots ausgewertet.</p>
<h4>Cookies</h4>
<p>Diese Website verwendet selbst keine Cookies. Eingebundene externe Module können jedoch Cookies einsetzen. Cookies können in den Browsereinstellungen deaktiviert werden. Bitte beachten Sie, dass dadurch einige Funktionen dieser Website möglicherweise eingeschränkt sind.</p>
<h4>Einbindung von Diensten Dritter</h4>
<p>Auf dieser Website können externe JavaScript-Bibliotheken und Inhalte von Drittanbietern eingebunden sein. Die Musikschule Frankfurt e.V. hat auf die datenschutzrechtlichen Bestimmungen einer Drittplattform sowie die Erhebung, Analyse und Nutzung von Userdaten keinen Einfluss.</p>
<h4>Auskunftsrecht</h4>
<p>Sie haben jederzeit das Recht auf unentgeltliche Auskunft über Ihre gespeicherten personenbezogenen Daten, deren Herkunft und Empfänger und den Zweck der Datenverarbeitung sowie ein Recht auf Berichtigung, Sperrung oder Löschung dieser Daten. Hierzu sowie zu weiteren Fragen zum Thema personenbezogene Daten können Sie sich jederzeit an uns wenden.</p>
<h4>Kontakt für Datenschutzfragen</h4>
<p>Musikschule Frankfurt e.V.<br>Berliner Straße 51<br>60311 Frankfurt am Main<br>E-Mail: datenschutz@musikschule-frankfurt.eu</p>
HTML,
        ]);

        Page::updateOrCreate(['slug' => 'kontakt'], [
            'title' => 'Kontakt',
            'content' => <<<'HTML'
<h3>Musikschule Frankfurt e.V.</h3>
<p>Berliner Straße 51<br>60311 Frankfurt am Main</p>
<h4>E-Mail</h4>
<p><a href="mailto:info@musikschule-frankfurt.de">info@musikschule-frankfurt.de</a></p>
<h4>Verwaltungszeiten</h4>
<p>Montag bis Freitag: 09:00 – 16:00 Uhr</p>
<h4>Internet</h4>
<p><a href="https://www.musikschule-frankfurt.de" target="_blank">www.musikschule-frankfurt.de</a></p>
HTML,
        ]);
    }
}
