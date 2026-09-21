<?php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionHashtagTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_appends_the_chosen_hashtag_to_the_content(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'I have never returned the library book.',
            'category' => 'confessions',
            'captchaToken' => 'test-token',
        ]);

        $response->assertRedirect(route('wall'));

        $this->assertSame(
            "I have never returned the library book.\n\n#confessions",
            Submission::query()->firstOrFail()->content,
        );
    }

    public function test_it_rejects_a_submission_without_a_category(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'No hashtag here.',
            'captchaToken' => 'test-token',
        ]);

        $response->assertSessionHasErrors('category');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_rejects_a_category_outside_the_allowed_list(): void
    {
        $response = $this->post('/submissions', [
            'content' => 'Making up my own tag.',
            'category' => 'notarealtag',
            'captchaToken' => 'test-token',
        ]);

        $response->assertSessionHasErrors('category');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_does_not_double_up_a_hashtag_the_author_already_typed(): void
    {
        $this->post('/submissions', [
            'content' => "Kilig moment sa hallway #kilig",
            'category' => 'kilig',
            'captchaToken' => 'test-token',
        ]);

        $this->assertSame(
            'Kilig moment sa hallway #kilig',
            Submission::query()->firstOrFail()->content,
        );
    }

    public function test_the_wall_page_exposes_the_category_list(): void
    {
        $this->get(route('wall'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Wall')
                ->where('categories', Submission::CATEGORIES));
    }
}
