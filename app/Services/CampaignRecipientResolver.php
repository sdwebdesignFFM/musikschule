<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignRecipientResolver
{
    /**
     * Loest die Student-IDs auf, die als CampaignRecipient angelegt werden sollen.
     * Soft-deleted Schueler werden in allen Modi gefiltert, damit Counter
     * (z.B. "X Empfaenger hinzugefuegt") nicht luegen und keine Geister-Pending
     * in den Statistiken auftauchen.
     *
     * @return array{ids: Collection<int, int>, listIds: array<int>}
     */
    public function resolve(string $mode, array $data): array
    {
        $studentIds = collect();
        $listIds = [];

        switch ($mode) {
            case 'alle_aktiven':
                $studentIds = Student::where('active', true)->pluck('id');
                break;

            case 'aus_listen':
                $listIds = array_values(array_filter((array) ($data['studentListIds'] ?? [])));

                if (! empty($listIds)) {
                    $studentIds = $studentIds->merge(
                        DB::table('student_list_members')
                            ->join('students', 'students.id', '=', 'student_list_members.student_id')
                            ->whereIn('student_list_id', $listIds)
                            ->whereNull('students.deleted_at')
                            ->pluck('student_list_members.student_id')
                    );
                }

                $extraIds = array_values(array_filter((array) ($data['extraStudentIds'] ?? [])));
                if (! empty($extraIds)) {
                    $studentIds = $studentIds->merge(
                        Student::whereIn('id', $extraIds)->pluck('id')
                    );
                }
                break;

            case 'manuell':
                $manualIds = array_values(array_filter((array) ($data['studentIds'] ?? [])));
                if (! empty($manualIds)) {
                    $studentIds = Student::whereIn('id', $manualIds)->pluck('id');
                }
                break;
        }

        return [
            'ids' => $studentIds->unique()->values(),
            'listIds' => $listIds,
        ];
    }
}
