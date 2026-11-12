<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VisitCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UniqueVisitCounterTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    public function test_a_first_visit_sets_the_cookie_and_counts_once(): void
    {
        $response = $this->withHeader('User-Agent', self::BROWSER)->get('/threads');

        $response->assertOk()->assertCookie(VisitCounter::COOKIE);
        $this->assertSame(1, DB::table('site_visits')->count());
        $this->assertSame('threads', DB::table('site_visits')->value('section'));

        // The stored value is a keyed hash, never the cookie itself.
        $cookie = $response->getCookie(VisitCounter::COOKIE, false)->getValue();
        $this->assertDatabaseMissing('site_visits', ['visitor' => $cookie]);
    }

    public function test_the_same_visitor_is_counted_once_per_section_per_day(): void
    {
        $id = (string) Str::uuid();

        foreach (['/threads', '/threads', '/jobs', '/jobs', '/tools'] as $path) {
            $this->flushSession();
            // Encrypted, as the browser sends back what the app set.
            $this->withCookie(VisitCounter::COOKIE, $id)
                ->withHeader('User-Agent', self::BROWSER)
                ->get($path)
                ->assertOk();
        }

        $this->assertSame(3, DB::table('site_visits')->count());
        $this->assertSame(1, DB::table('site_visits')->distinct()->count('visitor'));
    }

    public function test_bots_json_polling_and_admins_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/threads')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 HeadlessChrome/130.0')->get('/jobs')->assertOk();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'X-Requested-With' => 'XMLHttpRequest'])->get('/chat/messages')->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->withHeader('User-Agent', self::BROWSER)->get('/jobs')->assertOk();

        $this->assertSame(0, DB::table('site_visits')->count());
    }

    public function test_the_footer_and_the_admin_see_the_counts(): void
    {
        $counter = app(VisitCounter::class);
        $today = $counter->day()->toDateString();
        $yesterday = $counter->day()->subDay()->toDateString();

        DB::table('site_visits')->insert([
            ['visited_on' => $today, 'section' => 'wall', 'visitor' => str_repeat('a', 64)],
            ['visited_on' => $today, 'section' => 'jobs', 'visitor' => str_repeat('a', 64)],
            ['visited_on' => $today, 'section' => 'jobs', 'visitor' => str_repeat('b', 64)],
            ['visited_on' => $yesterday, 'section' => 'wall', 'visitor' => str_repeat('c', 64)],
        ]);

        $this->withHeader('User-Agent', 'curl/8')->get('/tools')->assertInertia(fn (Assert $page) => $page
            ->where('visitorStats', ['today' => 2, 'total' => 3]));

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson('/admin/visits')
            ->assertOk()
            ->assertJson([
                'today' => 2,
                'yesterday' => 1,
                'last7' => 3,
                'total' => 3,
                'sections' => [['section' => 'jobs', 'label' => 'Jobs', 'visitors' => 2], ['section' => 'wall', 'visitors' => 1]],
            ])
            ->assertJsonCount(30, 'days');

        $this->actingAs(User::factory()->create())->getJson('/admin/visits')->assertForbidden();
    }
}
