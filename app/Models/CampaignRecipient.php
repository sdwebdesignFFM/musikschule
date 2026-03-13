<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignRecipient extends Model
{
    protected $fillable = [
        'campaign_id',
        'student_id',
        'token',
        'status',
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
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignRecipient $recipient) {
            if (empty($recipient->token)) {
                $recipient->token = Str::random(64);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
