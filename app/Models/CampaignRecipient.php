<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'student_id',
        'token',
        'status',
        'email_status',
        'email_error',
        'send_attempts',
        'tracking_id',
        'email_opened_at',
        'email_clicked_at',
        'email_1_sent',
        'email_2_sent',
        'responded_at',
        'ip_address',
        'responded_via_email',
        'initial_sent_at',
        'reminder_1_sent_at',
        'reminder_2_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'initial_sent_at' => 'datetime',
            'reminder_1_sent_at' => 'datetime',
            'reminder_2_sent_at' => 'datetime',
            'email_opened_at' => 'datetime',
            'email_clicked_at' => 'datetime',
            'email_1_sent' => 'boolean',
            'email_2_sent' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignRecipient $recipient) {
            if (empty($recipient->token)) {
                $recipient->token = Str::random(64);
            }
            if (empty($recipient->tracking_id)) {
                $recipient->tracking_id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Atomares Locking: Setzt email_status auf 'sending', nur wenn nicht bereits 'sending'.
     * Gibt true zurück wenn Lock erworben, false wenn bereits locked.
     */
    public function acquireSendLock(): bool
    {
        $affected = DB::table('campaign_recipients')
            ->where('id', $this->id)
            ->where('email_status', '!=', 'sending')
            ->where('email_status', '!=', 'sent')
            ->update([
                'email_status' => 'sending',
                'send_attempts' => DB::raw('send_attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $this->refresh();
            return true;
        }

        return false;
    }

    public function markAsSent(): void
    {
        $this->update([
            'email_status' => 'sent',
            'email_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        // Wenn mindestens eine der beiden Adressen erfolgreich versendet wurde,
        // gilt der Empfänger als zugestellt — der Fehler betrifft nur die zweite
        // Adresse. Wir speichern den Fehler-Text, behalten aber Status 'sent'.
        if ($this->email_1_sent || $this->email_2_sent) {
            $this->update([
                'email_status' => 'sent',
                'email_error' => $error,
            ]);
            return;
        }

        $this->update([
            'email_status' => 'failed',
            'email_error' => $error,
        ]);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function student(): BelongsTo
    {
        // withTrashed: Ein Empfänger wurde zum Zeitpunkt des Imports einer
        // Kampagne zugewiesen. Wird der Student später (soft-)gelöscht, soll
        // der laufende Versand trotzdem funktionieren — der Consent bezog
        // sich auf den Datenstand zum Import.
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasResponded(): bool
    {
        return in_array($this->status, ['accepted', 'declined']);
    }
}
