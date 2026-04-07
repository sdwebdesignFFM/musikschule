<?php

namespace App\Console\Commands;

use App\Models\CampaignRecipient;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CampaignRepair extends Command
{
    protected $signature = 'campaign:repair {--dry-run : Nur anzeigen, nichts ändern} {--show-orphans : Verwaiste Recipients im Detail anzeigen}';

    protected $description = 'Repariert Kampagnen-Daten: Status-Inkonsistenzen, hängende Locks, Whitespace in Student-Daten';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // Sonder-Modus: nur Verwaiste anzeigen
        if ($this->option('show-orphans')) {
            return $this->showOrphans();
        }

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

        // 5) Verwaiste Recipients durch SoftDeletes von Students → Students wiederherstellen
        // Recipients haben student_id mit FK cascadeOnDelete, d.h. ein echter Hard-Delete
        // würde den Recipient mitlöschen. Verwaiste entstehen NUR durch SoftDeletes.
        $orphanStudentIds = CampaignRecipient::query()
            ->whereDoesntHave('student')
            ->pluck('student_id')
            ->unique()
            ->filter()
            ->values();

        $restorableCount = Student::onlyTrashed()
            ->whereIn('id', $orphanStudentIds)
            ->count();

        $this->line("5) SoftDeleted Students wiederherstellen (für verwaiste Recipients): {$restorableCount}");
        if (! $dry && $restorableCount > 0) {
            Student::onlyTrashed()
                ->whereIn('id', $orphanStudentIds)
                ->restore();
        }

        // Falls nach Restore noch Recipients ohne Student existieren (Hard-Delete-Edgecase),
        // diese als permanent failed markieren.
        $hardOrphanQuery = CampaignRecipient::query()
            ->whereDoesntHave('student')
            ->whereNotIn('email_status', ['failed']);
        $hardOrphans = $dry
            ? (clone $hardOrphanQuery)->count() - $restorableCount
            : (clone $hardOrphanQuery)->count();
        if ($hardOrphans > 0) {
            $this->line("   davon nicht wiederherstellbar (hard-deleted) → 'failed': {$hardOrphans}");
            if (! $dry) {
                (clone $hardOrphanQuery)->update([
                    'email_status' => 'failed',
                    'email_error' => 'Student wurde permanent gelöscht',
                ]);
            }
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

    private function showOrphans(): int
    {
        $this->info('=== Verwaiste Recipients (kein zugehöriger Student in Default-Query) ===');

        $orphanRecipients = CampaignRecipient::query()
            ->whereDoesntHave('student')
            ->orderBy('student_id')
            ->get();

        if ($orphanRecipients->isEmpty()) {
            $this->info('Keine verwaisten Recipients gefunden.');
            return self::SUCCESS;
        }

        $studentIds = $orphanRecipients->pluck('student_id')->unique()->filter();

        // Trashed Students (SoftDeleted) holen
        $trashed = Student::onlyTrashed()
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $rows = [];
        $restorable = 0;
        $hardDeleted = 0;
        foreach ($orphanRecipients as $r) {
            $student = $trashed->get($r->student_id);
            if ($student) {
                $restorable++;
                $rows[] = [
                    $r->id,
                    $r->student_id,
                    $student->customer_number,
                    $student->name,
                    $student->email,
                    'softdeleted ' . $student->deleted_at?->format('d.m.Y H:i'),
                    $r->email_status,
                ];
            } else {
                $hardDeleted++;
                $rows[] = [
                    $r->id,
                    $r->student_id,
                    '?',
                    '?',
                    '?',
                    'HARD DELETED',
                    $r->email_status,
                ];
            }
        }

        $this->table(
            ['Recipient', 'Student-ID', 'Kassenz.', 'Name', 'E-Mail', 'Zustand', 'Mail-Status'],
            $rows
        );

        $this->newLine();
        $this->info("Gesamt verwaist: {$orphanRecipients->count()}");
        $this->info("Wiederherstellbar (softdeleted): {$restorable}");
        $this->info("Permanent verloren (hard deleted): {$hardDeleted}");

        return self::SUCCESS;
    }
}
