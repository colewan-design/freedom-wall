<?php

namespace Tests\Feature;

use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_it_accepts_a_hashtag_the_author_wrote(): void
    {
        $this->submit('Third all-nighter this week.', 'studygrind')
            ->assertRedirect(route('wall'));

        $this->assertSame(
            "Third all-nighter this week.\n\n#studygrind",
            Submission::query()->firstOrFail()->content,
        );
    }

    /**
     * However it was typed, one spelling reaches the wall — otherwise #Rant
     * ends up sitting next to #rant.
     */
    public function test_it_normalizes_a_written_hashtag(): void
    {
        $this->submit('Finally submitted it.', '  #StudyGrind  ');

        $this->assertSame(
            "Finally submitted it.\n\n#studygrind",
            Submission::query()->firstOrFail()->content,
        );
    }

    /**
     * The wall reads a post's tag back out with /#([\w-]+)\s*$/, so a tag
     * outside that set would not render as a hashtag or leave the body text.
     */
    #[DataProvider('unusableHashtags')]
    public function test_it_rejects_a_hashtag_the_wall_could_not_read_back(string $category): void
    {
        $this->submit('Trying it on.', $category)->assertSessionHasErrors('category');

        $this->assertSame(0, Submission::query()->count());
    }

    /**
     * One case per test rather than a loop: submissions are throttled to three
     * per five minutes, so a loop would be rate-limited rather than validated.
     *
     * @return array<string, array{string}>
     */
    public static function unusableHashtags(): array
    {
        return [
            'contains a space' => ['study grind'],
            'contains punctuation' => ['study!'],
            'too short' => ['a'],
            'too long' => [str_repeat('x', 31)],
            'no letters at all' => ['123'],
        ];
    }

    public function test_it_runs_a_written_hashtag_through_the_content_filter(): void
    {
        $this->submit('Nothing wrong with the body.', 'retardtag')
            ->assertSessionHasErrors('category');

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_it_does_not_double_up_a_hashtag_the_author_already_typed(): void
    {
        $this->post('/submissions', [
            'content' => 'Kilig moment sa hallway #kilig',
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

    private function submit(string $content, string $category): TestResponse
    {
        return $this->post('/submissions', [
            'content' => $content,
            'category' => $category,
            'captchaToken' => 'test-token',
        ]);
    }
}
