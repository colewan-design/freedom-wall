<?php

namespace Tests\Feature\Admin;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveAllSubmissionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function submission(string $content, string $status = 'pending'): Submission
    {
        return Submission::query()->forceCreate([
            'content' => $content,
            'status' => $status,
            'submitted_at' => now(),
            'ip_hash' => hash('sha256', $content),
        ]);
    }

    public function test_admin_can_approve_the_whole_pending_queue(): void
    {
        $first = $this->submission('first #rant');
        $second = $this->submission('second #kilig');

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.submissions.approveAll'));

        $response->assertOk();
        $response->assertJson(['status' => 'approved', 'approved' => 2]);

        foreach ([$first, $second] as $submission) {
            $submission->refresh();
            $this->assertSame('approved', $submission->status);
            $this->assertFalse($submission->was_edited);
            $this->assertNotNull($submission->reviewed_at);
        }
    }

    public function test_edits_left_open_in_the_dashboard_are_kept(): void
    {
        $edited = $this->submission('typo here #rant');
        $untouched = $this->submission('leave me alone #kilig');

        $this->actingAs($this->admin())
            ->postJson(route('admin.submissions.approveAll'), [
                'edits' => [
                    (string) $edited->id => '  cleaned up #rant  ',
                    (string) $untouched->id => '',
                ],
            ])
            ->assertOk();

        $edited->refresh();
        $this->assertSame('cleaned up #rant', $edited->content);
        $this->assertTrue($edited->was_edited);

        $untouched->refresh();
        $this->assertSame('leave me alone #kilig', $untouched->content);
        $this->assertFalse($untouched->was_edited);
    }

    public function test_already_reviewed_submissions_are_left_alone(): void
    {
        $rejected = $this->submission('rejected #rant', 'rejected');
        $this->submission('still waiting #kilig');

        $this->actingAs($this->admin())
            ->postJson(route('admin.submissions.approveAll'))
            ->assertOk()
            ->assertJson(['approved' => 1]);

        $this->assertSame('rejected', $rejected->refresh()->status);
    }

    public function test_an_empty_queue_approves_nothing(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.submissions.approveAll'))
            ->assertOk()
            ->assertJson(['approved' => 0]);
    }

    public function test_students_cannot_approve_the_queue(): void
    {
        $submission = $this->submission('not yours to approve #rant');

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->postJson(route('admin.submissions.approveAll'))
            ->assertForbidden();

        $this->assertSame('pending', $submission->refresh()->status);
    }
}
