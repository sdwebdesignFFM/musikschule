<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageExpiredTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipient(array $campaignOverrides = [], array $recipientOverrides = []): CampaignRecipient
    {
        $campaign = Campaign::create(array_merge([
            'name' => 'Vertragsänderung 2026',
            'status' => 'active',
            'start_date' => now()->subWeek()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ], $campaignOverrides));

        $student = Student::factory()->create();

        return CampaignRecipient::create(array_merge([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ], $recipientOverrides));
    }

    public function test_landing_page_is_open_when_deadline_is_today(): void
    {
        $recipient = $this->makeRecipient(['deadline' => now()->toDateString()]);

        $response = $this->get('/k/' . $recipient->token);

        $response->assertStatus(200);
        $response->assertDontSee('Die Kampagne ist beendet');
    }

    public function test_landing_page_shows_expired_view_when_deadline_passed(): void
    {
        $recipient = $this->makeRecipient(['deadline' => now()->subDay()->toDateString()]);

        $response = $this->get('/k/' . $recipient->token);

        $response->assertStatus(200);
        $response->assertSee('Die Kampagne ist beendet');
        $response->assertSee('Die Rückmeldefrist ist überschritten', false);
    }

    public function test_respond_is_blocked_after_deadline_and_does_not_persist_status(): void
    {
        $recipient = $this->makeRecipient(['deadline' => now()->subDay()->toDateString()]);

        $response = $this->post('/k/' . $recipient->token . '/respond', [
            'response' => 'accepted',
        ]);

        $response->assertStatus(200);
        $response->assertSee('Die Kampagne ist beendet');

        $recipient->refresh();
        $this->assertSame('pending', $recipient->status);
        $this->assertNull($recipient->responded_at);
    }

    public function test_landing_page_shows_expired_when_status_completed_even_if_deadline_future(): void
    {
        $recipient = $this->makeRecipient([
            'status' => 'completed',
            'deadline' => now()->addWeek()->toDateString(),
        ]);

        $response = $this->get('/k/' . $recipient->token);

        $response->assertStatus(200);
        $response->assertSee('Die Kampagne ist beendet');
    }

    public function test_responded_view_wins_over_expired_view_for_users_who_answered(): void
    {
        $recipient = $this->makeRecipient(
            ['deadline' => now()->subDay()->toDateString()],
            ['status' => 'accepted', 'responded_at' => now()->subDays(3)],
        );

        $response = $this->get('/k/' . $recipient->token);

        $response->assertStatus(200);
        $response->assertSee('Wurde schon abgeschlossen');
        $response->assertDontSee('Die Kampagne ist beendet');
    }
}
