<?php

namespace Tests\Unit;

use App\Models\Student;
use App\Models\StudentList;
use App\Services\CampaignRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    private CampaignRecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CampaignRecipientResolver();
    }

    public function test_alle_aktiven_returns_only_active_students(): void
    {
        $active = Student::factory()->count(3)->create(['active' => true]);
        Student::factory()->count(2)->create(['active' => false]);

        $result = $this->resolver->resolve('alle_aktiven', []);

        $this->assertCount(3, $result['ids']);
        $this->assertEqualsCanonicalizing($active->pluck('id')->all(), $result['ids']->all());
        $this->assertEmpty($result['listIds']);
    }

    public function test_aus_listen_excludes_soft_deleted_members(): void
    {
        $alive = Student::factory()->create();
        $deleted = Student::factory()->create();

        $list = StudentList::create(['name' => 'Test']);
        $list->allMembers()->attach([$alive->id, $deleted->id]);

        $deleted->delete();

        $result = $this->resolver->resolve('aus_listen', [
            'studentListIds' => [$list->id],
        ]);

        $this->assertCount(1, $result['ids']);
        $this->assertSame([$alive->id], $result['ids']->all());
        $this->assertSame([$list->id], $result['listIds']);
    }

    public function test_aus_listen_merges_lists_and_extras_with_dedup(): void
    {
        $a = Student::factory()->create();
        $b = Student::factory()->create();
        $c = Student::factory()->create();

        $list = StudentList::create(['name' => 'L']);
        $list->allMembers()->attach([$a->id, $b->id]);

        $result = $this->resolver->resolve('aus_listen', [
            'studentListIds' => [$list->id],
            'extraStudentIds' => [$b->id, $c->id],
        ]);

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id],
            $result['ids']->all()
        );
    }

    public function test_manuell_takes_only_provided_ids(): void
    {
        $students = Student::factory()->count(3)->create();
        $picked = $students->take(2)->pluck('id')->all();

        $result = $this->resolver->resolve('manuell', [
            'studentIds' => $picked,
        ]);

        $this->assertEqualsCanonicalizing($picked, $result['ids']->all());
        $this->assertEmpty($result['listIds']);
    }

    public function test_unknown_mode_returns_empty_collection(): void
    {
        Student::factory()->count(3)->create();

        $result = $this->resolver->resolve('unbekannt', []);

        $this->assertTrue($result['ids']->isEmpty());
        $this->assertEmpty($result['listIds']);
    }
}
