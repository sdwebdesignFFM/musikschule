<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description'];

    /**
     * Mitglieder-Sicht fuer Anzeige/Counter — soft-deleted Schueler ausgeblendet.
     * NICHT fuer Filament-Form-Binding verwenden, sonst arbeitet sync() auf
     * gefilterten Pivot-Rows und Geister-Members bleiben stehen.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_list_members')
            ->whereNull('students.deleted_at')
            ->withTimestamps();
    }

    /**
     * Ungefilterte Mitglieder-Relation fuer Form-Binding (RelationManager,
     * Multi-Select beim Liste-Edit) und fuer den Snapshot-Schritt — sync()
     * arbeitet so auf allen Pivot-Rows.
     */
    public function allMembers(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_list_members')
            ->withTimestamps();
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_student_list')
            ->withTimestamps();
    }
}
