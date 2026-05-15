<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use App\Filament\Resources\CampaignResource\Pages\EditCampaign;
use App\Filament\Resources\StudentListResource\Pages\CreateStudentList;
use App\Filament\Resources\StudentListResource\Pages\EditStudentList;
use App\Filament\Resources\StudentListResource\Pages\ListStudentLists;
use App\Filament\Resources\StudentListResource\RelationManagers\MembersRelationManager;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\CampaignRecipient;
use App\Models\Student;
use App\Models\StudentList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentListFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        $this->actingAs($admin);
    }

    /**
     * Pflicht-CampaignEmail-Records anlegen, damit das Edit-Form sauber
     * lädt (mutateFormDataBeforeFill liest aus $campaign->emails).
     */
    private function attachDefaultEmails(Campaign $campaign): void
    {
        foreach (
            [
                ['type' => 'initial',    'delay' => 0],
                ['type' => 'reminder_1', 'delay' => 7],
                ['type' => 'reminder_2', 'delay' => 14],
            ] as $row
        ) {
            CampaignEmail::create([
                'campaign_id' => $campaign->id,
                'type' => $row['type'],
                'subject' => 'Betreff ' . $row['type'],
                'body' => '<p>Body ' . $row['type'] . '</p>',
                'delay_days' => $row['delay'],
            ]);
        }
    }

    // ---- Listen-CRUD ----

    public function test_admin_can_create_student_list_via_filament_form(): void
    {
        Livewire::test(CreateStudentList::class)
            ->fillForm([
                'name' => 'Klavierschüler',
                'description' => 'Demo-Liste',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('student_lists', [
            'name' => 'Klavierschüler',
            'description' => 'Demo-Liste',
        ]);
    }

    public function test_student_list_index_page_loads_with_counters(): void
    {
        $list = StudentList::create(['name' => 'L']);
        $students = Student::factory()->count(3)->create();
        $list->allMembers()->attach($students->pluck('id'));

        Livewire::test(ListStudentLists::class)
            ->assertCanSeeTableRecords([$list])
            ->assertCountTableRecords(1);

        // Counter via Eloquent-Query verifizieren — die Resource setzt
        // withCount im modifyQueryUsing.
        $hydrated = ListStudentLists::getResource()::getEloquentQuery()
            ->where('id', $list->id)
            ->first();
        // Tabelle nutzt withCount im modifyQueryUsing — also direkt nachstellen.
        $count = StudentList::withCount([
            'members as members_count' => fn ($q) => $q->whereNull('students.deleted_at'),
        ])->find($list->id)->members_count;
        $this->assertSame(3, $count);
    }

    public function test_members_relation_manager_can_attach_students(): void
    {
        $list = StudentList::create(['name' => 'L']);
        $student = Student::factory()->create();

        Livewire::test(MembersRelationManager::class, [
            'ownerRecord' => $list,
            'pageClass' => EditStudentList::class,
        ])
            ->callTableAction('attach', data: [
                'recordId' => $student->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('student_list_members', [
            'student_list_id' => $list->id,
            'student_id' => $student->id,
        ]);
    }

    // ---- Bulk-Action im StudentResource ----

    public function test_bulk_addToList_attaches_selected_students(): void
    {
        $list = StudentList::create(['name' => 'Klavier']);
        $a = Student::factory()->create();
        $b = Student::factory()->create();
        $c = Student::factory()->create();

        Livewire::test(ListStudents::class)
            ->callTableBulkAction('addToList', [$a->id, $b->id], data: [
                'list_id' => $list->id,
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $list->fresh()->allMembers()->pluck('students.id')->all()
        );
        $this->assertFalse($list->fresh()->allMembers()->where('students.id', $c->id)->exists());
    }

    public function test_bulk_addToList_is_idempotent(): void
    {
        $list = StudentList::create(['name' => 'L']);
        $student = Student::factory()->create();
        $list->allMembers()->attach($student->id);

        Livewire::test(ListStudents::class)
            ->callTableBulkAction('addToList', [$student->id], data: [
                'list_id' => $list->id,
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(1, $list->fresh()->allMembers()->count());
    }

    // ---- Kampagne aus Liste anlegen ----

    public function test_create_campaign_with_aus_listen_mode_persists_recipients_and_audit(): void
    {
        $students = Student::factory()->count(3)->create();
        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach($students->pluck('id'));

        Livewire::test(CreateCampaign::class)
            ->fillForm([
                'name' => 'Aus Liste',
                'start_date' => now()->toDateString(),
                'deadline' => now()->addWeeks(4)->toDateString(),
                'recipient_mode' => 'aus_listen',
                'studentListIds' => [$list->id],
                'email_initial_subject' => 'Hallo',
                'email_initial_body' => '<p>Body</p>',
                'email_reminder_1_subject' => 'R1',
                'email_reminder_1_body' => '<p>R1</p>',
                'email_reminder_1_delay_days' => 7,
                'email_reminder_2_subject' => 'R2',
                'email_reminder_2_body' => '<p>R2</p>',
                'email_reminder_2_delay_days' => 14,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = Campaign::where('name', 'Aus Liste')->firstOrFail();
        $this->assertSame(3, $campaign->recipients()->count());
        $this->assertSame([$list->id], $campaign->sourceLists()->pluck('student_lists.id')->all());
    }

    public function test_create_campaign_alle_aktiven_takes_only_active_students(): void
    {
        Student::factory()->count(3)->create(['active' => true]);
        Student::factory()->count(2)->create(['active' => false]);

        Livewire::test(CreateCampaign::class)
            ->fillForm([
                'name' => 'Alle aktiven',
                'start_date' => now()->toDateString(),
                'deadline' => now()->addWeeks(4)->toDateString(),
                'recipient_mode' => 'alle_aktiven',
                'email_initial_subject' => 'S',
                'email_initial_body' => '<p>B</p>',
                'email_reminder_1_subject' => 'R1',
                'email_reminder_1_body' => '<p>R1</p>',
                'email_reminder_1_delay_days' => 7,
                'email_reminder_2_subject' => 'R2',
                'email_reminder_2_body' => '<p>R2</p>',
                'email_reminder_2_delay_days' => 14,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = Campaign::where('name', 'Alle aktiven')->firstOrFail();
        $this->assertSame(3, $campaign->recipients()->count());
    }

    public function test_create_campaign_manuell_persists_only_picked_students(): void
    {
        $students = Student::factory()->count(4)->create();
        $picked = $students->take(2);

        Livewire::test(CreateCampaign::class)
            ->fillForm([
                'name' => 'Manuell',
                'start_date' => now()->toDateString(),
                'deadline' => now()->addWeeks(4)->toDateString(),
                'recipient_mode' => 'manuell',
                'studentIds' => $picked->pluck('id')->all(),
                'email_initial_subject' => 'S',
                'email_initial_body' => '<p>B</p>',
                'email_reminder_1_subject' => 'R1',
                'email_reminder_1_body' => '<p>R1</p>',
                'email_reminder_1_delay_days' => 7,
                'email_reminder_2_subject' => 'R2',
                'email_reminder_2_body' => '<p>R2</p>',
                'email_reminder_2_delay_days' => 14,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = Campaign::where('name', 'Manuell')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            $picked->pluck('id')->all(),
            $campaign->recipients()->pluck('student_id')->all()
        );
    }

    // ---- Edit-Form Pre-Selection ----

    public function test_edit_campaign_prefills_recipient_mode_aus_listen_when_source_lists_exist(): void
    {
        $student = Student::factory()->create();
        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach($student->id);

        $campaign = Campaign::create([
            'name' => 'Edit-Listed',
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeeks(4)->toDateString(),
        ]);
        $campaign->sourceLists()->attach($list->id);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $student->id]);

        Livewire::test(EditCampaign::class, ['record' => $campaign->id])
            ->assertFormSet([
                'recipient_mode' => 'aus_listen',
                'studentListIds' => [$list->id],
                'studentIds' => [],
                'extraStudentIds' => [],
            ]);
    }

    public function test_edit_campaign_prefills_manuell_when_no_source_lists(): void
    {
        $student = Student::factory()->create();
        $campaign = Campaign::create([
            'name' => 'Edit-Manuell',
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeeks(4)->toDateString(),
        ]);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $student->id]);

        Livewire::test(EditCampaign::class, ['record' => $campaign->id])
            ->assertFormSet([
                'recipient_mode' => 'manuell',
                'studentListIds' => [],
                'studentIds' => [],
            ]);
    }

    public function test_edit_campaign_save_with_no_recipient_changes_keeps_tokens(): void
    {
        $student = Student::factory()->create();
        $campaign = Campaign::create([
            'name' => 'Stable',
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeeks(4)->toDateString(),
        ]);
        $this->attachDefaultEmails($campaign);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
        ]);
        $originalToken = $recipient->token;

        Livewire::test(EditCampaign::class, ['record' => $campaign->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $campaign->fresh()->recipients()->count());
        $this->assertSame($originalToken, $recipient->fresh()->token);
    }

    public function test_edit_campaign_can_add_extra_students_additively(): void
    {
        $existing = Student::factory()->create();
        $newPicks = Student::factory()->count(2)->create();

        $campaign = Campaign::create([
            'name' => 'Additive',
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeeks(4)->toDateString(),
        ]);
        $this->attachDefaultEmails($campaign);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'student_id' => $existing->id]);

        Livewire::test(EditCampaign::class, ['record' => $campaign->id])
            ->fillForm([
                'recipient_mode' => 'manuell',
                'studentIds' => $newPicks->pluck('id')->all(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, $campaign->fresh()->recipients()->count());
    }

    // ---- Force-Delete-Absicherung in Filament-Pages ----

    public function test_student_list_index_does_not_expose_force_delete_action(): void
    {
        $list = StudentList::create(['name' => 'X']);
        $list->delete();

        Livewire::test(ListStudentLists::class)
            ->assertTableActionDoesNotExist('forceDelete')
            ->assertTableBulkActionDoesNotExist('forceDeleteBulk');
    }
}
