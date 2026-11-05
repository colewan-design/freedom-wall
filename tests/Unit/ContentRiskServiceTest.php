<?php

namespace Tests\Unit;

use App\Services\ContentRiskService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentRiskServiceTest extends TestCase
{
    private ContentRiskService $risk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->risk = new ContentRiskService;
    }

    #[DataProvider('contactPosts')]
    public function test_it_flags_someone_elses_contact_details(string $text): void
    {
        $this->assertTrue(
            $this->risk->assess($text)->has('contact_details'),
            "Expected [$text] to be flagged as contact details.",
        );
    }

    #[DataProvider('targetedPosts')]
    public function test_it_flags_a_named_person_with_an_insult(string $text): void
    {
        $this->assertTrue(
            $this->risk->assess($text)->has('named_target'),
            "Expected [$text] to be flagged as a named target.",
        );
    }

    #[DataProvider('crisisPosts')]
    public function test_it_flags_a_post_that_sounds_like_a_crisis(string $text): void
    {
        $this->assertTrue(
            $this->risk->assess($text)->has('self_harm'),
            "Expected [$text] to be flagged as self harm.",
        );
    }

    #[DataProvider('ordinaryPosts')]
    public function test_it_leaves_ordinary_posts_alone(string $text): void
    {
        $assessment = $this->risk->assess($text);

        $this->assertTrue(
            $assessment->isClean(),
            "Expected [$text] to be clean, flagged: ".json_encode($assessment->flags),
        );
    }

    public function test_an_insult_with_nobody_on_the_other_end_is_not_a_target(): void
    {
        $this->assertTrue($this->risk->assess('Nakakahiya yung grades ko this sem, ang bobo ko talaga')->isClean());
    }

    public function test_a_name_with_nothing_unkind_attached_is_not_a_target(): void
    {
        $this->assertTrue($this->risk->assess('Happy birthday kay Maria! Sana all ganito ka-bait.')->isClean());
    }

    public function test_scores_stack_and_cap_at_one_hundred(): void
    {
        $assessment = $this->risk->assess('Si Maria ang pinaka sinungaling dito, number niya 09171234567, ikalat niyo');

        $this->assertTrue($assessment->has('named_target'));
        $this->assertTrue($assessment->has('contact_details'));
        $this->assertSame(100, $assessment->score);
        $this->assertSame('high', $assessment->level());
    }

    public function test_a_clean_post_scores_nothing(): void
    {
        $assessment = $this->risk->assess('Anyone know if the library is open on Saturday?');

        $this->assertSame(0, $assessment->score);
        $this->assertSame('none', $assessment->level());
        $this->assertSame([], $assessment->categories());
    }

    public function test_it_can_be_switched_off(): void
    {
        $off = new ContentRiskService(enabled: false);

        $this->assertTrue($off->assess('Si Maria ay sinungaling, 09171234567')->isClean());
    }

    public function test_the_evidence_comes_back_with_the_flag(): void
    {
        $flags = $this->risk->assess('Text niya sa 0917-123-4567, si Maria yan')->flags;

        $this->assertContains('0917-123-4567', $flags['contact_details']);
    }

    public static function contactPosts(): array
    {
        return [
            'phone, plain' => ['Si Jhen ang nangbubully, number niya 09171234567'],
            'phone, spaced' => ['Number niya 0917 123 4567, tawagan niyo'],
            'phone, dashed' => ['Kay Maria to: 0917-123-4567'],
            'phone, country code' => ['Contact niya +63 917 123 4567'],
            'email' => ['Email niya jhen.santos@g.batstate-u.edu.ph, spam niyo'],
            'facebook profile' => ['Profile niya facebook.com/jhen.santos.391, report niyo'],
            'student number' => ['Student number niya 2023-01234, ipakalat niyo'],
        ];
    }

    public static function targetedPosts(): array
    {
        return [
            'tagalog particle' => ['Si Maria ang pinaka sinungaling sa section namin'],
            'kay particle' => ['Wag kayo magtiwala kay Jhen, manloloko yan'],
            'handle' => ['@jhensantos ang plastik mo talaga'],
            'section code spaced' => ['Yung BSIT 2-A, puro sila mandaraya sa exam'],
            'section code joined' => ['Ang baba ng BSIT-2A, bobo lahat ng nandoon'],
            'english full name' => ['Maria Santos is such a liar, everyone knows it'],
            'call-out without an insult word' => ['Iwasan niyo si Jhen, seryoso ako'],
        ];
    }

    public static function crisisPosts(): array
    {
        return [
            'tagalog' => ['Pagod na ako sa buhay, gusto ko na mamatay'],
            'english' => ['I want to die, wala nang point'],
            'indirect' => ['This is my last post, salamat sa lahat'],
            'self harm' => ['I keep hurting myself and I dont know why'],
        ];
    }

    public static function ordinaryPosts(): array
    {
        return [
            // The wall's bread and butter: venting at nobody in particular.
            'rant at no one' => ['Ang init sa room 304, nakakainis mag-aral dito #rant'],
            'rant with insult, no target' => ['Ang bobo ko sa Calculus, bagsak na naman ako'],

            // Selling and losing things, which is most of what gets posted.
            'selling with own number' => ['For sale: scientific calculator 500 only, text me 09171234567'],
            'own contact, english' => ['Looking for a tutor in Stats, message me at 0917 123 4567'],
            'lost id with own email' => ['Lost my ID near the oval, email me at juan.cruz@gmail.com'],

            // Things that look like identifiers but are not.
            'school year' => ['Enrollment for SY 2024-2025 starts on Monday, nakakainis ang haba ng pila'],
            'course without a section' => ['Any BSIT students here? Ang hirap ng finals'],
            'allowlisted name pair' => ['Nakakahiya na ang Freedom Wall, puro na lang chismis'],
            'non-person particle' => ['Ang bobo ng system, yung Calculus module ang hirap'],

            // Kind posts about named people.
            'greeting' => ['Happy anniversary kay Maria at Juan, sana all'],
            'thanks' => ['Thank you kay Sir Dela Cruz sa review materials'],
        ];
    }
}
