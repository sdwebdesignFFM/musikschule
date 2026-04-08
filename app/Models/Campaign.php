<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'subtitle',
        'description',
        'document_section_title',
        'checkbox_text',
        'accept_text',
        'decline_text',
        'status',
        'start_date',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(CampaignEmail::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CampaignDocument::class)->orderBy('sort_order');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Recipients deren Student tatsächlich noch existiert (nicht softgelöscht).
     * Wird in der UI verwendet, damit verwaiste Empfänger nicht mitgezählt werden.
     */
    public function validRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class)
            ->whereExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.id', 'campaign_recipients.student_id')
                    ->whereNull('students.deleted_at');
            });
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'campaign_recipients')
            ->withPivot('token', 'status', 'responded_at', 'initial_sent_at', 'reminder_1_sent_at', 'reminder_2_sent_at')
            ->withTimestamps();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isSendable(): bool
    {
        return $this->isActive();
    }
}
