<?php

namespace App\Console\Commands;

use App\Models\CampaignRecipient;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CampaignRepair extends Command
{
    protected $signature = 'campaign:repair {--dry-run : Nur anzeigen, nichts ändern}';

    protected $description = 'Repariert Kampagnen-Daten: Status-Inkonsistenzen, hängende Locks, Whitespace in Student-Daten';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? '=== DRY RUN (keine Änderungen) ===' : '=== REPAIR (mit Änderungen) ===');

        // 1) 'failed' Recipients, die eigentlich versendet wurden → auf 'sent'
        $failedButSentQuery = CampaignRecipient::query()
            ->where('email_status', 'failed')
            ->where(function ($q) {
                $q->where('email_1_sent', true)->orWhere('email_2_sent', true);
            });
        $failedButSent = (clone $failedButSentQuery)->count();
        $this->line("1) Recipients 'failed' aber e1/e2=sent → 'sent': {$failedButSent}");
        if (! $dry && $failedButSent > 0) {
            (clone $failedButSentQuery)->update(['email_status' => 'sent']);
        }

        // 2) Hängende 'sending' Locks (nichts versendet) → zurück auf 'pending'
        $stuckSendingQuery = CampaignRecipient::query()
            ->where('email_status', 'sending')
            ->where('email_1_sent', false)
            ->where('email_2_sent', false);
        $stuckSending = (clone $stuckSendingQuery)->count();
        $this->line("2) Recipients 'sending' aber nichts versendet → 'pending': {$stuckSending}");
        if (! $dry && $stuckSending > 0) {
            (clone $stuckSendingQuery)->update([
                'email_status' => 'pending',
                'email_error' => null,
            ]);
        }

        // 3) 'sending' aber e1=1 (teilweise versendet) → 'sent'
        $partialSendingQuery = CampaignRecipient::query()
            ->where('email_status', 'sending')
            ->where(function ($q) {
                $q->where('email_1_sent', true)->orWhere('email_2_sent', true);
            });
        $partialSending = (clone $partialSendingQuery)->count();
        $this->line("3) Recipients 'sending' mit e1/e2=sent → 'sent': {$partialSending}");
        if (! $dry && $partialSending > 0) {
            (clone $partialSendingQuery)->update(['email_status' => 'sent']);
        }

        // 4) Whitespace in Students bereinigen (NBSP, ZWSP, Trailing-Space)
        $nbsp = "\xC2\xA0";
        $zwsp = "\xE2\x80\x8B";
        $cleaned = 0;
        Student::query()->chunkById(500, function ($students) use (&$cleaned, $dry, $nbsp, $zwsp) {
            foreach ($students as $s) {
                $original = [
                    'name' => $s->name,
                    'email' => $s->email,
                    'email_2' => $s->email_2,
                    'customer_number' => $s->customer_number,
                ];
                $s->name = trim(str_replace([$nbsp, $zwsp], '', (string) $s->name));
                $s->email = trim(str_replace([$nbsp, $zwsp], '', (string) $s->email));
                if ($s->email_2 !== null) {
                    $s->email_2 = trim(str_replace([$nbsp, $zwsp], '', (string) $s->email_2));
                    if ($s->email_2 === '') {
                        $s->email_2 = null;
                    }
                }
                $s->customer_number = trim(str_replace([$nbsp, $zwsp], '', (string) $s->customer_number));

                if ($s->isDirty()) {
                    $cleaned++;
                    if (! $dry) {
                        $s->saveQuietly();
                    }
                }
            }
        });
        $this->line("4) Students mit bereinigtem Whitespace: {$cleaned}");

        // 5) Permanent verwaiste Recipients (Student gelöscht) → als 'failed' fixieren
        $orphanQuery = CampaignRecipient::query()
            ->whereDoesntHave('student')
            ->whereNotIn('email_status', ['failed']);
        $orphans = (clone $orphanQuery)->count();
        $this->line("5) Recipients ohne Student → 'failed' fixieren: {$orphans}");
        if (! $dry && $orphans > 0) {
            (clone $orphanQuery)->update([
                'email_status' => 'failed',
                'email_error' => 'Student wurde gelöscht',
            ]);
        }

        $this->newLine();
        $this->info($dry ? 'DRY RUN abgeschlossen. Zum Anwenden ohne --dry-run aufrufen.' : 'Repair abgeschlossen.');

        // Statusübersicht
        $this->newLine();
        $this->info('=== Aktueller Status ===');
        $stats = DB::table('campaign_recipients')
            ->select('email_status', DB::raw('COUNT(*) as count'))
            ->groupBy('email_status')
            ->get();
        foreach ($stats as $row) {
            $this->line(sprintf('  %-10s %d', $row->email_status, $row->count));
        }

        return self::SUCCESS;
    }
}
