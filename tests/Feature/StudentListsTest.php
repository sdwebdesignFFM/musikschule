<?php

namespace Tests\Feature;

use App\Filament\Resources\CampaignResource;
use App\Filament\Resources\StudentListResource;
use App\Filament\Resources\StudentResource;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Student;
use App\Models\StudentList;
use App\Services\CampaignRecipientResolver;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentListsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCampaign(array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'name' => 'Test-Kampagne',
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeeks(4)->toDateString(),
        ], $overrides));
    }

    public function test_t1_list_with_members_persists_correctly(): void
    {
        $students = Student::factory()->count(3)->create();
        $list = StudentList::create(['name' => 'Klasse A']);

        $list->allMembers()->attach($students->pluck('id'));

        $this->assertDatabaseCount('student_list_members', 3);
        $this->assertEquals(3, $list->members()->count());
    }

    public function test_t2_campaign_from_list_creates_one_recipient_per_member(): void
    {
        $students = Student::factory()->count(4)->create();
        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach($students->pluck('id'));

        $campaign = $this->makeCampaign();

        $resolver = new CampaignRecipientResolver();
        $resolved = $resolver->resolve('aus_listen', ['studentListIds' => [$list->id]]);
        $campaign->sourceLists()->syncWithoutDetaching($resolved['listIds']);
        foreach ($resolved['ids'] as $sid) {
            CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $sid]);
        }

        $this->assertEquals(4, $campaign->recipients()->count());
        $this->assertEquals([$list->id], $campaign->sourceLists()->pluck('student_lists.id')->all());
    }

    public function test_t3_list_plus_extras_deduplicates(): void
    {
        $a = Student::factory()->create();
        $b = Student::factory()->create();
        $c = Student::factory()->create();

        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach([$a->id, $b->id]);

        $campaign = $this->makeCampaign();
        $resolver = new CampaignRecipientResolver();
        $resolved = $resolver->resolve('aus_listen', [
            'studentListIds' => [$list->id],
            'extraStudentIds' => [$b->id, $c->id],
        ]);

        foreach ($resolved['ids'] as $sid) {
            CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $sid]);
        }

        $this->assertEquals(3, $campaign->recipients()->count());
    }

    public function test_t4_snapshot_soft_delete_does_not_remove_recipient(): void
    {
        $student = Student::factory()->create();
        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach($student->id);

        $campaign = $this->makeCampaign();
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $student->id]);

        // Schueler aus der Liste entfernen + soft-deleten
        $list->allMembers()->detach($student->id);
        $student->delete();

        $this->assertEquals(1, $campaign->recipients()->count());
        $this->assertNotNull(CampaignRecipient::where('campaign_id', $campaign->id)->first());
    }

    public function test_t5_same_student_in_two_campaigns_has_independent_tokens(): void
    {
        $student = Student::factory()->create();
        $a = $this->makeCampaign(['name' => 'A']);
        $b = $this->makeCampaign(['name' => 'B']);

        $ra = CampaignRecipient::create(['campaign_id' => $a->id, 'student_id' => $student->id]);
        $rb = CampaignRecipient::create(['campaign_id' => $b->id, 'student_id' => $student->id]);

        $this->assertNotEquals($ra->token, $rb->token);

        $ra->update(['status' => 'accepted', 'responded_at' => now()]);
        $this->assertSame('accepted', $ra->fresh()->status);
        $this->assertSame('pending', $rb->fresh()->status);
    }

    public function test_t6_addToList_deduplicates_via_syncWithoutDetaching(): void
    {
        $list = StudentList::create(['name' => 'L']);
        $existing = Student::factory()->create();
        $list->allMembers()->attach($existing->id);

        $new = Student::factory()->count(2)->create();
        $allIds = collect([$existing->id])->merge($new->pluck('id'));

        $list->allMembers()->syncWithoutDetaching($allIds->all());

        $this->assertEquals(3, $list->allMembers()->count());
        $this->assertEquals(3, $list->members()->count());
    }

    public function test_t7_soft_deleted_list_keeps_audit_and_recipients(): void
    {
        $student = Student::factory()->create();
        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach($student->id);

        $campaign = $this->makeCampaign();
        $campaign->sourceLists()->attach($list->id);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $student->id]);

        $list->delete();

        $this->assertEquals(1, $campaign->recipients()->count());
        $this->assertEquals(
            [$list->id],
            $campaign->sourceLists()->withTrashed()->pluck('student_lists.id')->all()
        );
    }

    public function test_t8_campaigns_count_ignores_soft_deleted_campaigns(): void
    {
        $list = StudentList::create(['name' => 'L']);
        $alive = $this->makeCampaign(['name' => 'Alive']);
        $deleted = $this->makeCampaign(['name' => 'Deleted']);

        $alive->sourceLists()->attach($list->id);
        $deleted->sourceLists()->attach($list->id);
        $deleted->delete();

        $count = StudentList::withCount([
            'campaigns as campaigns_count' => fn ($q) => $q->whereNull('campaigns.deleted_at'),
        ])->find($list->id)->campaigns_count;

        $this->assertSame(1, $count);
    }

    public function test_t9_force_delete_destroys_recipient_documented_grenze(): void
    {
        $student = Student::factory()->create();
        $campaign = $this->makeCampaign();
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $student->id]);

        $student->forceDelete();

        // cascadeOnDelete entfernt den Recipient — dokumentierte Garantie-Grenze.
        $this->assertEquals(0, $campaign->recipients()->count());
    }

    public function test_t10_members_count_excludes_soft_deleted_students(): void
    {
        $alive = Student::factory()->create();
        $deleted = Student::factory()->create();

        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach([$alive->id, $deleted->id]);
        $deleted->delete();

        $count = StudentList::withCount([
            'members as members_count' => fn ($q) => $q->whereNull('students.deleted_at'),
        ])->find($list->id)->members_count;

        $this->assertSame(1, $count);
    }

    public function test_t11_filter_in_lists_uses_or_semantics(): void
    {
        $studentA = Student::factory()->create();
        $studentB = Student::factory()->create();
        $studentC = Student::factory()->create();

        $listA = StudentList::create(['name' => 'A']);
        $listB = StudentList::create(['name' => 'B']);
        $listA->allMembers()->attach($studentA->id);
        $listB->allMembers()->attach($studentB->id);

        $result = Student::whereHas('studentLists', fn ($q) =>
            $q->whereIn('student_lists.id', [$listA->id, $listB->id])
        )->pluck('id');

        $this->assertEqualsCanonicalizing([$studentA->id, $studentB->id], $result->all());
        $this->assertNotContains($studentC->id, $result->all());
    }

    public function test_t12_syncWithoutDetaching_keeps_old_audit_entries(): void
    {
        $campaign = $this->makeCampaign();
        $a = StudentList::create(['name' => 'A']);
        $b = StudentList::create(['name' => 'B']);

        $campaign->sourceLists()->syncWithoutDetaching([$a->id]);
        $campaign->sourceLists()->syncWithoutDetaching([$b->id]);

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $campaign->sourceLists()->pluck('student_lists.id')->all()
        );
    }

    public function test_t13_restore_makes_list_visible_again(): void
    {
        $list = StudentList::create(['name' => 'L']);
        $list->delete();

        $this->assertSoftDeleted('student_lists', ['id' => $list->id]);

        $list->restore();

        $this->assertNotNull(StudentList::find($list->id));
    }

    public function test_t14_force_delete_actions_are_not_registered_in_any_resource(): void
    {
        // Smoke-Check via Code-Inspection: keine der Resources baut explizit
        // ForceDelete-Actions ein. Wir grep'en die Resource-Dateien.
        $files = [
            base_path('app/Filament/Resources/StudentResource.php'),
            base_path('app/Filament/Resources/StudentListResource.php'),
            base_path('app/Filament/Resources/CampaignResource.php'),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            $this->assertStringNotContainsString('ForceDeleteAction::make', $contents, "Resource {$file} darf keine ForceDeleteAction registrieren");
            $this->assertStringNotContainsString('ForceDeleteBulkAction::make', $contents, "Resource {$file} darf keine ForceDeleteBulkAction registrieren");
        }
    }

    public function test_t15_edit_pre_selection_mode_heuristik(): void
    {
        // Mit Liste -> 'aus_listen'
        $listCampaign = $this->makeCampaign();
        $list = StudentList::create(['name' => 'L']);
        $listCampaign->sourceLists()->attach($list->id);

        $listIds = $listCampaign->sourceLists()->pluck('student_lists.id')->all();
        $mode = ! empty($listIds) ? 'aus_listen' : 'manuell';
        $this->assertSame('aus_listen', $mode);

        // Ohne Liste, mit Empfaengern -> 'manuell'
        $manuellCampaign = $this->makeCampaign(['name' => 'Manuell']);
        CampaignRecipient::create([
            'campaign_id' => $manuellCampaign->id,
            'student_id' => Student::factory()->create()->id,
        ]);

        $listIds2 = $manuellCampaign->sourceLists()->pluck('student_lists.id')->all();
        $mode2 = ! empty($listIds2) ? 'aus_listen' : 'manuell';
        $this->assertSame('manuell', $mode2);
    }

    public function test_t17_saving_existing_campaign_does_not_change_tokens(): void
    {
        $student = Student::factory()->create();
        $campaign = $this->makeCampaign();
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
        ]);
        $originalToken = $recipient->token;

        // Edit-Save mit leeren Selects (Pre-Selection-Logik) — die Resolver-
        // Diff-Logik darf keinen Recipient veraendern oder duplizieren.
        $resolver = new CampaignRecipientResolver();
        $resolved = $resolver->resolve('manuell', ['studentIds' => []]);
        $existing = $campaign->recipients()->pluck('student_id');
        $toAdd = $resolved['ids']->diff($existing);
        $this->assertTrue($toAdd->isEmpty());

        $this->assertEquals(1, $campaign->recipients()->count());
        $this->assertEquals($originalToken, $recipient->fresh()->token);
    }

    public function test_t21_start_modal_shows_count_and_warns_on_overlap(): void
    {
        $student = Student::factory()->create();
        $oldCampaign = $this->makeCampaign(['name' => 'Alt']);
        $newCampaign = $this->makeCampaign(['name' => 'Neu']);

        // Alter Recipient hat schon initial_sent_at vor 3 Tagen
        CampaignRecipient::create([
            'campaign_id' => $oldCampaign->id,
            'student_id' => $student->id,
            'status' => 'pending',
            'initial_sent_at' => now()->subDays(3),
        ]);

        // Neuer Recipient ist noch pending
        CampaignRecipient::create([
            'campaign_id' => $newCampaign->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $html = (string) CampaignResource::buildStartModalDescription($newCampaign);

        $this->assertStringContainsString('Diese Kampagne wird an', $html);
        $this->assertStringContainsString('<strong>1</strong>', $html);
        $this->assertStringContainsString('letzten 7 Tagen', $html);
    }

    public function test_t21b_start_modal_no_warning_without_overlap(): void
    {
        $student = Student::factory()->create();
        $campaign = $this->makeCampaign();
        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $html = (string) CampaignResource::buildStartModalDescription($campaign);

        $this->assertStringContainsString('Diese Kampagne wird an', $html);
        $this->assertStringNotContainsString('letzten 7 Tagen', $html);
    }

    public function test_import_with_target_list_attaches_new_students(): void
    {
        $list = StudentList::create(['name' => 'Importliste']);

        $import = new \App\Imports\StudentsImport($list);
        // Direkt das model() aufrufen, ohne tatsaechliche Excel-Datei
        $row = [
            'kassenzeichen' => 'MS-9001',
            'name' => 'Import Test',
            'email' => 'import@test.de',
            'email_2' => null,
        ];
        $student = $import->model($row);

        $this->assertNotNull($student);
        $this->assertSame(
            [$list->id],
            $student->fresh()->studentLists()->pluck('student_lists.id')->all()
        );
    }

    public function test_import_without_target_list_does_not_attach(): void
    {
        $import = new \App\Imports\StudentsImport(null);
        $student = $import->model([
            'kassenzeichen' => 'MS-9002',
            'name' => 'Solo',
            'email' => 'solo@test.de',
            'email_2' => null,
        ]);

        $this->assertSame(0, $student->fresh()->studentLists()->count());
    }

    public function test_import_with_target_list_is_idempotent_for_existing_student(): void
    {
        $existing = Student::factory()->create(['customer_number' => 'MS-9003']);
        $list = StudentList::create(['name' => 'Reimport']);
        $list->allMembers()->attach($existing->id);

        $import = new \App\Imports\StudentsImport($list);
        $import->model([
            'kassenzeichen' => 'MS-9003',
            'name' => 'Update',
            'email' => 'update@test.de',
            'email_2' => null,
        ]);

        $this->assertSame(1, $list->fresh()->allMembers()->count());
    }

    public function test_students_export_contains_list_column(): void
    {
        $student = Student::factory()->create();
        $a = StudentList::create(['name' => 'A']);
        $b = StudentList::create(['name' => 'B']);
        $a->allMembers()->attach($student->id);
        $b->allMembers()->attach($student->id);

        $export = new \App\Exports\StudentsExport();
        $headings = $export->headings();
        $row = $export->map($student->fresh()->load('studentLists'));

        $this->assertContains('In Listen', $headings);
        $listColumn = $row[array_search('In Listen', $headings)];
        $this->assertStringContainsString('A', $listColumn);
        $this->assertStringContainsString('B', $listColumn);
    }

    public function test_t23_latestResponse_returns_real_answer_across_campaigns(): void
    {
        $student = Student::factory()->create();
        $oldCampaign = $this->makeCampaign(['name' => 'Alt']);
        $newCampaign = $this->makeCampaign(['name' => 'Neu']);

        // Aelter, mit akzeptierter Antwort
        CampaignRecipient::create([
            'campaign_id' => $oldCampaign->id,
            'student_id' => $student->id,
            'status' => 'accepted',
            'responded_at' => now()->subDays(10),
        ]);

        // Neuer, noch pending
        CampaignRecipient::create([
            'campaign_id' => $newCampaign->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $latest = $student->latestResponse();

        $this->assertNotNull($latest);
        $this->assertSame('accepted', $latest->status);
    }
}
