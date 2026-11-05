<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_a_submission_on_the_way_in(): void
    {
        $this->post('/submissions', [
            'content' => 'Si Maria ang sinungaling, number niya 09171234567',
            'category' => 'rant',
            'captchaToken' => 'test-token',
        ])->assertRedirect(route('wall'));

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(100, $submission->risk_score);
        $this->assertSame('high', $submission->risk_level);
        $this->assertNotNull($submission->risk_checked_at);
        $this->assertArrayHasKey('contact_details', $submission->risk_flags);
    }

    public function test_a_flagged_submission_is_still_accepted_and_queued(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'Iwasan niyo si Jhen, manloloko yan',
            'category' => 'rant',
            'captchaToken' => 'test-token',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionMissing('errors');

        // The whole point: flagging reorders the queue, it never rejects.
        $this->assertSame('pending', Submission::query()->firstOrFail()->status);
    }

    public function test_an_ordinary_post_scores_nothing(): void
    {
        $this->post('/submissions', [
            'content' => 'Is the library open on Saturday?',
            'category' => 'question',
            'captchaToken' => 'test-token',
        ]);

        $submission = Submission::query()->firstOrFail();

        $this->assertSame(0, $submission->risk_score);
        $this->assertNull($submission->risk_flags);
        $this->assertNotNull($submission->risk_checked_at);
    }

    public function test_a_crisis_post_gets_support_details_back(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'Pagod na ako sa buhay, gusto ko na mamatay',
            'category' => 'confessions',
            'captchaToken' => 'test-token',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('support');

        $this->assertSame('pending', Submission::query()->firstOrFail()->status);
    }

    public function test_the_pending_queue_puts_flagged_posts_first(): void
    {
        $clean = Submission::create([
            'content' => 'Ang init sa room 304',
            'ip_hash' => 'hash-1',
            'risk_score' => 0,
        ]);

        $flagged = Submission::create([
            'content' => 'Si Maria ay sinungaling',
            'ip_hash' => 'hash-2',
            'risk_score' => 45,
        ]);

        // The clean one landed first, so only the score can reorder them.
        $this->assertTrue($clean->id < $flagged->id);

        $items = $this->actingAs($this->admin())
            ->getJson('/admin/submissions?status=pending')
            ->assertOk()
            ->json('items');

        $this->assertSame($flagged->id, $items[0]['id']);
        $this->assertSame('medium', $items[0]['risk_level']);
        $this->assertSame($clean->id, $items[1]['id']);
    }

    public function test_rejecting_records_why(): void
    {
        $submission = Submission::create(['content' => 'Si Maria ay sinungaling', 'ip_hash' => 'hash-3']);

        $this->actingAs($this->admin())
            ->postJson("/admin/submissions/{$submission->id}/reject", ['reason' => 'bullying'])
            ->assertOk();

        $this->assertSame('bullying', $submission->fresh()->rejection_reason);
    }

    public function test_an_unknown_rejection_reason_falls_back_to_other(): void
    {
        $submission = Submission::create(['content' => 'Buy my calculator', 'ip_hash' => 'hash-4']);

        $this->actingAs($this->admin())
            ->postJson("/admin/submissions/{$submission->id}/reject", ['reason' => 'because i said so'])
            ->assertOk();

        $this->assertSame('other', $submission->fresh()->rejection_reason);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
