<?php

namespace Tests\Unit;

use App\Services\ContentFilterService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentFilterServiceTest extends TestCase
{
    private ContentFilterService $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new ContentFilterService;
    }

    #[DataProvider('blockedPosts')]
    public function test_it_rejects_trash_talk(string $text): void
    {
        $this->assertTrue(
            $this->filter->containsBlockedContent($text),
            "Expected [$text] to be rejected.",
        );
    }

    #[DataProvider('innocentPosts')]
    public function test_it_lets_ordinary_posts_through(string $text): void
    {
        $this->assertFalse(
            $this->filter->containsBlockedContent($text),
            "Expected [$text] to be allowed, found: ".implode(', ', $this->filter->blockedTerms($text)),
        );
    }

    public static function blockedPosts(): array
    {
        return [
            // Plain.
            'filipino' => ['putangina mo talaga'],
            'two words' => ['putang ina naman'],
            'tagalog insult' => ['ang bobo mo'],
            'english' => ['what the fuck is this'],
            'slur' => ['stop being a retard'],

            // Casing and accents.
            'uppercase' => ['GAGO KA BA'],
            'mixed case' => ['TaNgInA nito'],
            'accented' => ['pütangina'],

            // Lookalike characters.
            'zeroes' => ['p0tangina mo'],
            'at sign' => ['ang @sshole mo'],
            'ones' => ['sh1t naman'],
            'v for u' => ['fvck this'],

            // Stretched and masked letters.
            'stretched' => ['puuutaaangina'],
            'masked' => ['what the f**k'],
            'single mask' => ['p*tangina'],

            // Letters pushed apart.
            'spaces' => ['f u c k you'],
            'dots' => ['p.u.t.a.n.g.i.n.a'],
            'dashes' => ['g-a-g-o ka'],
            'zero width space' => ["fu\u{200B}ck you"],

            // Endings.
            'tagalog ending' => ['gagong tao'],
            'english ending' => ['fucking hell'],
            'plural' => ['these bitches'],

            // Inside a longer message.
            'mid sentence' => ['nice weather today but tangina ang init'],
        ];
    }

    public static function innocentPosts(): array
    {
        return [
            'empty' => [''],
            'ordinary' => ['Good luck sa finals everyone!'],

            // Innocent words that contain a blocked term.
            'suspicious' => ['That looks suspicious to me'],
            'assignment' => ['Ang dami kong assignment this week'],
            'assume' => ['Do not assume things about me'],
            'class' => ['Late na naman ako sa class'],
            'analysis' => ['Our data analysis is due tomorrow'],
            'cumulative' => ['My cumulative GPA is fine'],
            'cocktail' => ['We had cocktail drinks after'],
            'batangas' => ['Taga Batangas ako'],
            'tanggap' => ['Tanggap ko na ang resulta'],
            'titig' => ['Ang tagal mong titig sa akin'],
            'spicy' => ['Ang spicy ng sisig dito'],
            'cumin' => ['Add a pinch of cumin'],
            'hello' => ['Hello po, good morning'],
            'grape' => ['Bumili ako ng grape juice'],

            // Escape-hatch phrases from config/profanity.php.
            'lady gaga' => ['Lady Gaga concert daw sa Manila'],
            'titis' => ['May titis ng sigarilyo sa sahig'],
            'maine coon' => ['Ang cute ng maine coon niya'],

            // A lone mask is not a word.
            'stars' => ['*** this is just emphasis ***'],
        ];
    }

    public function test_it_reports_the_words_it_found(): void
    {
        $this->assertSame(['gago'], $this->filter->blockedTerms('gago ka'));
    }

    public function test_it_can_be_turned_off(): void
    {
        $filter = new ContentFilterService(enabled: false);

        $this->assertFalse($filter->containsBlockedContent('putangina'));
    }

    public function test_it_uses_the_terms_it_is_given(): void
    {
        $filter = new ContentFilterService(blocked: ['banned'], allowed: []);

        $this->assertTrue($filter->containsBlockedContent('that is b a n n e d'));
        $this->assertFalse($filter->containsBlockedContent('putangina'));
    }
}
