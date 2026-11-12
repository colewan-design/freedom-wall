<?php

namespace Tests\Feature;

use App\Mail\JobDigestMail;
use App\Models\JobListing;
use App\Models\JobMonitor;
use App\Models\JobMonitorHit;
use App\Models\JobSave;
use App\Models\JobSource;
use App\Models\User;
use App\Services\Jobs\JobFetcher;
use App\Services\Jobs\JobFingerprint;
use App\Services\Jobs\JobMatcher;
use App\Services\Jobs\JobSafety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobRadarTest extends TestCase
{
    use RefreshDatabase;

    // --- Discovery --------------------------------------------------------

    public function test_rss_feed_items_become_pending_listings(): void
    {
        Http::fake(['example.test/*' => Http::response($this->rss([
            ['IT Officer I (Contractual)', 'https://example.test/jobs/1', 'Handle the office network and helpdesk.'],
            ['Web Developer - Full Time', 'https://example.test/jobs/2', 'Laravel and Vue.'],
        ]))]);

        $source = $this->source('rss', ['url' => 'https://example.test/feed', 'employer' => 'Benguet State University', 'location' => 'La Trinidad, Benguet']);

        $counts = app(JobFetcher::class)->run($source);

        $this->assertSame(['new' => 2, 'merged' => 0, 'seen' => 0, 'skipped' => 0], $counts);
        $this->assertSame(2, JobListing::query()->pending()->count());

        $officer = JobListing::query()->where('title', 'like', 'IT Officer%')->sole();
        $this->assertSame('contractual', $officer->employment_type);
        $this->assertSame('La Trinidad, Benguet', $officer->location);
        $this->assertSame('government', $officer->sector);
        $this->assertSame('ok', $source->fresh()->last_status);
    }

    public function test_reading_the_same_feed_twice_does_not_duplicate(): void
    {
        Http::fake(['example.test/*' => Http::response($this->rss([
            ['Programmer', 'https://example.test/jobs/9', 'PHP.'],
        ]))]);

        $source = $this->source('rss', ['url' => 'https://example.test/feed', 'employer' => 'Acme']);

        app(JobFetcher::class)->run($source);
        $second = app(JobFetcher::class)->run($source->fresh(), force: true);

        $this->assertSame(['new' => 0, 'merged' => 0, 'seen' => 1, 'skipped' => 0], $second);
        $this->assertSame(1, JobListing::query()->count());
    }

    public function test_the_same_vacancy_from_two_sources_merges_into_one_listing(): void
    {
        Http::fake([
            'boards-api.greenhouse.io/*' => Http::response(['jobs' => [[
                'id' => 501,
                'title' => 'Information Systems Analyst',
                'absolute_url' => 'https://boards.greenhouse.io/acme/jobs/501',
                'location' => ['name' => 'Baguio City'],
                'content' => '&lt;p&gt;Analyse systems.&lt;/p&gt;',
                'updated_at' => '2026-11-10T08:00:00Z',
            ]]]),
            'api.lever.co/*' => Http::response([[
                'id' => 'abc-123',
                'text' => 'Hiring: Information Systems Analyst',
                'hostedUrl' => 'https://jobs.lever.co/acme/abc-123',
                'categories' => ['location' => 'Baguio City', 'commitment' => 'Full-time'],
                'createdAt' => 1762761600000,
                'descriptionPlain' => 'Analyse systems.',
            ]]),
        ]);

        $greenhouse = $this->source('greenhouse', ['board' => 'acme', 'employer' => 'Acme Corp'], autoApprove: true);
        $lever = $this->source('lever', ['company' => 'acme', 'employer' => 'Acme Corporation'], autoApprove: true);

        app(JobFetcher::class)->run($greenhouse);
        $counts = app(JobFetcher::class)->run($lever);

        $this->assertSame(1, $counts['merged']);

        $listing = JobListing::query()->sole();
        $this->assertSame(2, $listing->sources()->count());
        $this->assertSame('approved', $listing->status);
        // The second source filled the gap the first left.
        $this->assertSame('full-time', $listing->employment_type);
        $this->assertSame('Analyse systems.', $listing->description);
    }

    public function test_remotive_listings_are_remote_and_keep_their_salary(): void
    {
        Http::fake(['remotive.com/*' => Http::response(['jobs' => [[
            'id' => 77,
            'url' => 'https://remotive.com/remote-jobs/software-dev/vue-dev-77',
            'title' => 'Vue Developer',
            'company_name' => 'Distributed Co',
            'job_type' => 'contract',
            'publication_date' => '2026-11-09T10:00:00',
            'candidate_required_location' => 'Asia',
            'salary' => '$2,000 - $3,000 / month',
            'description' => '<p>Build things.</p>',
        ]]])]);

        app(JobFetcher::class)->run($this->source('remotive', ['search' => 'vue']));

        $listing = JobListing::query()->sole();
        $this->assertTrue($listing->is_remote);
        $this->assertSame('Remote · Asia', $listing->location);
        $this->assertSame('contractual', $listing->employment_type);
        $this->assertSame('$2,000 - $3,000 / month', $listing->salary_text);
    }

    public function test_a_flagged_posting_from_a_trusted_feed_still_waits_for_review(): void
    {
        Http::fake(['example.test/*' => Http::response($this->rss([
            ['Encoder', 'https://example.test/jobs/3', 'Pay a processing fee of 500 before your interview.'],
        ]))]);

        app(JobFetcher::class)->run($this->source('rss', ['url' => 'https://example.test/feed', 'employer' => 'Acme'], autoApprove: true));

        $listing = JobListing::query()->sole();
        $this->assertSame('pending', $listing->status);
        $this->assertArrayHasKey('upfront_fee', $listing->safety_flags);
    }

    public function test_a_failing_source_records_the_error_and_does_not_throw(): void
    {
        Http::fake(['example.test/*' => Http::response('nope', 500)]);

        $source = $this->source('rss', ['url' => 'https://example.test/feed']);

        $this->assertNull(app(JobFetcher::class)->run($source));
        $this->assertSame('error', $source->fresh()->last_status);
        $this->assertStringContainsString('500', $source->fresh()->last_error);
    }

    public function test_remotive_is_not_read_more_often_than_its_terms_allow(): void
    {
        $source = $this->source('remotive', ['interval_minutes' => 30]);
        $source->forceFill(['last_fetched_at' => now()->subHours(2)])->save();

        $this->assertFalse(app(JobFetcher::class)->isDue($source));

        $source->forceFill(['last_fetched_at' => now()->subHours(7)])->save();
        $this->assertTrue(app(JobFetcher::class)->isDue($source));
    }

    public function test_listings_gone_from_every_feed_are_closed(): void
    {
        $listing = $this->listing();
        $source = $this->source('rss', ['url' => 'https://example.test/feed']);
        $listing->sources()->create([
            'job_source_id' => $source->id,
            'external_id' => 'x',
            'url' => 'https://example.test/x',
            'label' => 'Feed',
            'last_seen_at' => now()->subDays(20),
        ]);

        $community = $this->listing(['title' => 'Shared by a reader']);
        $community->sources()->create(['url' => 'https://example.test/y', 'label' => 'example.test', 'last_seen_at' => now()->subDays(20)]);

        $this->artisan('jobs:fetch')->assertSuccessful();

        $this->assertSame('closed', $listing->fresh()->status);
        $this->assertSame('approved', $community->fresh()->status);
    }

    public function test_a_vacancy_page_becomes_listings_with_salary_type_and_deadline(): void
    {
        $deadline = now()->addDays(8);
        $row = fn (string $code, string $title, string $kind, string $pay) => '<li class="job-row"><div class="job-row-main"><h2><a href="/jobs/'.$code.'">'.$title.'</a></h2>'
            .'<p><span class="badge">CBAO</span><span>'.$kind.'</span><span>Items needed: 1</span></p></div>'
            .'<div class="job-row-side"><strong>'.$pay.'</strong><span>Apply by '.$deadline->format('F j, Y').'</span><span>8 days left</span></div></li>';

        Http::fake([
            'hris.example.test/robots.txt' => Http::response('', 404),
            'hris.example.test/jobs' => Http::response('<html><body><a href="/jobs">Job Openings</a><a href="/jobs/how-to-apply">How to apply</a><ul>'
                .$row('AbC123', 'Engineer I (Civil Engineer)', 'PERMANENT', 'P 33,947.00 monthly')
                .$row('XyZ789', 'Traffic Aide I', 'CASUAL', 'P 749.36 daily')
                .'</ul></body></html>'),
        ]);

        $source = $this->source('page', ['url' => 'https://hris.example.test/jobs', 'link_contains' => '/jobs/', 'employer' => 'City Government of Baguio', 'location' => 'Baguio City'], autoApprove: true);

        $this->assertSame(['new' => 2, 'merged' => 0, 'seen' => 0, 'skipped' => 0], app(JobFetcher::class)->run($source));

        $engineer = JobListing::query()->where('title', 'Engineer I (Civil Engineer)')->sole();
        $this->assertSame('approved', $engineer->status);
        $this->assertSame('https://hris.example.test/jobs/AbC123', $engineer->apply_url);
        $this->assertSame('full-time', $engineer->employment_type);
        $this->assertSame('government', $engineer->sector);
        $this->assertSame('P 33,947.00 monthly', $engineer->salary_text);
        $this->assertSame($deadline->toDateString(), $engineer->deadline->toDateString());
        $this->assertStringNotContainsString('days left', $engineer->description);

        $aide = JobListing::query()->where('title', 'Traffic Aide I')->sole();
        $this->assertSame('contractual', $aide->employment_type);
        $this->assertSame('P 749.36 daily', $aide->salary_text);
    }

    public function test_a_page_disallowed_by_robots_txt_is_not_read(): void
    {
        Http::fake([
            'blocked.example.test/robots.txt' => Http::response("User-agent: *\nDisallow: /careers\n"),
            'blocked.example.test/careers*' => Http::response('<a href="/careers/1">Clerk</a>'),
        ]);

        $source = $this->source('page', ['url' => 'https://blocked.example.test/careers', 'link_contains' => '/careers/']);

        $this->assertNull(app(JobFetcher::class)->run($source));
        $this->assertStringContainsString('robots.txt', $source->fresh()->last_error);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://blocked.example.test/careers');
    }

    public function test_a_page_behind_a_bot_check_is_left_alone(): void
    {
        Http::fake([
            'gov.example.test/robots.txt' => Http::response('', 404),
            'gov.example.test/*' => Http::response('Just a moment...', 403),
        ]);

        $source = $this->source('page', ['url' => 'https://gov.example.test/vacancies', 'link_contains' => '/vacancy/']);

        $this->assertNull(app(JobFetcher::class)->run($source));
        $this->assertStringContainsString('403', $source->fresh()->last_error);
        $this->assertSame(0, JobListing::query()->count());
    }

    public function test_rss_reads_job_board_tags_and_filters_by_location(): void
    {
        $xml = '<?xml version="1.0"?><rss version="2.0" xmlns:job_listing="https://jobicy.com"><channel>'
            .'<item><title>Support Specialist</title><link>https://board.example.test/1</link><guid>1</guid>'
            .'<job_listing:company><![CDATA[Acme]]></job_listing:company><job_listing:location><![CDATA[Philippines]]></job_listing:location>'
            .'<job_listing:job_type><![CDATA[Part Time]]></job_listing:job_type><description>Help customers.</description></item>'
            .'<item><title>Account Executive</title><link>https://board.example.test/2</link><guid>2</guid>'
            .'<job_listing:company><![CDATA[Globex]]></job_listing:company><job_listing:location><![CDATA[UK, Germany]]></job_listing:location>'
            .'<description>Sell.</description></item>'
            .'</channel></rss>';

        Http::fake(['board.example.test/*' => Http::response($xml)]);

        app(JobFetcher::class)->run($this->source('rss', ['url' => 'https://board.example.test/feed', 'remote' => true, 'locations' => ['Philippines', 'Asia']], autoApprove: true));

        $listing = JobListing::query()->sole();
        $this->assertSame('Support Specialist', $listing->title);
        $this->assertSame('Acme', $listing->employer);
        $this->assertSame('Remote · Philippines', $listing->location);
        $this->assertTrue($listing->is_remote);
        $this->assertSame('part-time', $listing->employment_type);
        $this->assertSame('approved', $listing->status);
    }

    public function test_rss_can_split_the_employer_out_of_the_title(): void
    {
        Http::fake(['wwr.example.test/*' => Http::response($this->rss([
            ['Smart Suites: Operations Manager', 'https://wwr.example.test/1', 'Run things.'],
        ]))]);

        app(JobFetcher::class)->run($this->source('rss', ['url' => 'https://wwr.example.test/feed', 'employer_in_title' => true]));

        $listing = JobListing::query()->sole();
        $this->assertSame('Operations Manager', $listing->title);
        $this->assertSame('Smart Suites', $listing->employer);
    }

    public function test_an_overlong_location_is_trimmed_to_fit_the_column(): void
    {
        $countries = implode(', ', array_fill(0, 40, '🇦🇹 Austria'));
        $xml = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>Zanda Health: Product Owner</title>'
            .'<link>https://wwr.example.test/9</link><guid>9</guid><region>Anywhere in the World</region>'
            .'<country>'.$countries.'</country><description>Own the product.</description></item></channel></rss>';

        Http::fake(['wwr.example.test/*' => Http::response($xml)]);

        $counts = app(JobFetcher::class)->run($this->source('rss', ['url' => 'https://wwr.example.test/feed', 'remote' => true, 'employer_in_title' => true]));

        $this->assertSame(1, $counts['new']);
        $location = JobListing::query()->sole()->location;
        $this->assertLessThanOrEqual(160, mb_strlen($location));
        $this->assertStringStartsWith('Remote · Anywhere in the World', $location);
        $this->assertStringEndsWith('…', $location);
    }

    public function test_company_boards_can_be_limited_to_philippine_openings(): void
    {
        Http::fake(['api.lever.co/*' => Http::response([
            ['id' => 'a', 'text' => 'Rider Coordinator', 'hostedUrl' => 'https://jobs.lever.co/x/a', 'categories' => ['location' => 'Taguig, Philippines']],
            ['id' => 'b', 'text' => 'Warehouse Lead', 'hostedUrl' => 'https://jobs.lever.co/x/b', 'categories' => ['location' => 'Shah Alam, Selangor, Malaysia']],
        ])]);

        app(JobFetcher::class)->run($this->source('lever', ['company' => 'x', 'employer' => 'Ninja Van', 'locations' => 'Philippines']));

        $this->assertSame(['Rider Coordinator'], JobListing::query()->pluck('title')->all());
    }

    // --- Fingerprint, safety, matching -----------------------------------

    public function test_fingerprint_ignores_noise_words_and_company_suffixes(): void
    {
        $this->assertSame(
            JobFingerprint::for('URGENT HIRING: Web Developer', 'Acme Corp.'),
            JobFingerprint::for('Web Developer', 'acme'),
        );

        $this->assertNotSame(
            JobFingerprint::for('Web Developer', 'Acme'),
            JobFingerprint::for('Web Designer', 'Acme'),
        );
    }

    public function test_safety_labels_shortened_links_and_daily_pay_lures(): void
    {
        $flags = app(JobSafety::class)->assess(new JobListing([
            'title' => 'Online encoder',
            'employer' => null,
            'apply_url' => 'https://bit.ly/abc',
            'description' => 'Earn 8,000 per day from home!',
        ]));

        $this->assertEqualsCanonicalizing(['suspicious_link', 'missing_employer', 'unrealistic_pay'], array_keys($flags));
    }

    public function test_matcher_scores_title_location_and_type(): void
    {
        $monitor = new JobMonitor([
            'keywords' => ['Information Systems Analyst', 'Web Developer'],
            'location' => 'Baguio City and Benguet',
            'employment_types' => ['full-time', 'contractual'],
            'remote_ok' => true,
            'min_score' => 50,
        ]);

        $listing = new JobListing([
            'title' => 'Information Systems Analyst II',
            'location' => 'La Trinidad, Benguet',
            'employment_type' => 'full-time',
            'discovered_at' => now(),
        ]);

        $result = app(JobMatcher::class)->score($monitor, $listing);

        $this->assertSame(100, $result['score']);
        $this->assertSame(['Job title', 'Location', 'Employment type', 'Newly posted'], $result['reasons']);

        $unrelated = new JobListing(['title' => 'Nurse', 'location' => 'Baguio City', 'discovered_at' => now()]);
        $this->assertSame(0, app(JobMatcher::class)->score($monitor, $unrelated)['score']);
    }

    // --- Public board ----------------------------------------------------

    public function test_the_board_lists_only_approved_listings_and_filters(): void
    {
        $this->listing(['title' => 'Web Developer', 'location' => 'Baguio City', 'employment_type' => 'full-time']);
        $this->listing(['title' => 'Accountant', 'location' => 'Manila', 'employment_type' => 'part-time']);
        $this->listing(['title' => 'Hidden one', 'status' => 'pending']);

        $this->get('/jobs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Index')
            ->has('listings.data', 2)
            ->where('insights.open', 2)
            ->where('canTrack', false));

        $this->get('/jobs?q=web+developer&location=baguio&type=full-time')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listings.data', 1)->where('listings.data.0.title', 'Web Developer'));
    }

    public function test_a_listing_page_says_what_is_missing_and_hides_pending_ones(): void
    {
        $listing = $this->listing(['salary_text' => null, 'deadline' => null]);
        $listing->sources()->create(['url' => $listing->apply_url, 'label' => 'example.test']);

        $this->get("/jobs/{$listing->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Show')
            ->where('listing.missing', ['salary', 'deadline'])
            ->has('listing.sources', 1));

        $pending = $this->listing(['status' => 'pending']);
        $this->get("/jobs/{$pending->id}")->assertNotFound();
    }

    public function test_anyone_can_share_a_job_and_it_waits_for_review(): void
    {
        $this->post('/jobs', [
            'apply_url' => 'https://careers.example.test/it-officer',
            'title' => 'IT Officer',
            'employer' => 'City Government of Baguio',
            'location' => 'Baguio City',
            'employment_type' => 'contractual',
        ])->assertRedirect('/jobs')->assertSessionHas('success');

        $listing = JobListing::query()->sole();
        $this->assertSame('pending', $listing->status);
        $this->assertSame('government', $listing->sector);
        $this->assertSame(1, $listing->sources()->count());
    }

    public function test_sharing_a_job_already_on_the_board_adds_a_source_instead(): void
    {
        $existing = $this->listing(['title' => 'Web Developer', 'employer' => 'Acme']);

        $this->post('/jobs', [
            'apply_url' => 'https://facebook.example.test/acme/posts/1',
            'title' => 'Hiring Web Developer',
            'employer' => 'Acme Inc.',
        ])->assertRedirect('/jobs');

        $this->assertSame(1, JobListing::query()->count());
        $this->assertSame(1, $existing->sources()->count());
    }

    public function test_sharing_rejects_non_web_links(): void
    {
        $this->post('/jobs', ['apply_url' => 'javascript:alert(1)', 'title' => 'x'])
            ->assertSessionHasErrors('apply_url');
    }

    public function test_three_reports_pull_a_listing_back_into_the_queue(): void
    {
        $listing = $this->listing();

        foreach (range(1, 3) as $i) {
            // Each report from a fresh session, as three different readers.
            $this->flushSession();
            $this->postJson("/jobs/{$listing->id}/report", ['reason' => 'scam'])->assertOk();
        }

        $this->assertSame('pending', $listing->fresh()->status);
        $this->assertSame(3, $listing->fresh()->safety_flags['reported']);
    }

    // --- Student tools ---------------------------------------------------

    public function test_guests_are_sent_to_log_in_for_my_jobs(): void
    {
        $this->get('/jobs/mine')->assertRedirect('/login');
        $this->post('/jobs/1/save')->assertRedirect('/login');
    }

    public function test_a_student_can_save_and_track_a_job(): void
    {
        $student = User::factory()->create();
        $listing = $this->listing();

        $this->actingAs($student)->post("/jobs/{$listing->id}/save")->assertRedirect();

        $save = JobSave::query()->sole();
        $this->assertSame('interested', $save->status);

        $this->actingAs($student)->patch("/job-saves/{$save->id}", [
            'status' => 'applied',
            'applied_on' => '2026-11-12',
            'notes' => 'Sent PDS and TOR.',
        ])->assertRedirect();

        $this->assertSame('applied', $save->fresh()->status);

        $other = User::factory()->create();
        $this->actingAs($other)->patch("/job-saves/{$save->id}", ['status' => 'hired'])->assertNotFound();
    }

    public function test_a_new_monitor_backfills_and_new_approvals_reach_it(): void
    {
        $student = User::factory()->create();
        $this->listing(['title' => 'Web Developer', 'location' => 'Baguio City']);

        $this->actingAs($student)->post('/job-monitors', [
            'name' => 'IT in Baguio',
            'keywords' => 'Web Developer, Programmer',
            'location' => 'Baguio',
            'min_score' => 50,
            'frequency' => 'daily',
            'remote_ok' => true,
            'email_alerts' => true,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');

        $monitor = JobMonitor::query()->sole();
        $this->assertSame(['Web Developer', 'Programmer'], $monitor->keywords);
        $this->assertSame(1, $monitor->hits()->count());
        // Backfilled matches are not emailed again.
        $this->assertNotNull($monitor->hits()->first()->emailed_at);

        $pending = $this->listing(['title' => 'Junior Programmer', 'location' => 'Baguio City', 'status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson("/admin/jobs/listings/{$pending->id}/approve")
            ->assertOk()
            ->assertJson(['matched' => 1]);

        $this->assertNull(JobMonitorHit::query()->where('job_listing_id', $pending->id)->sole()->emailed_at);
    }

    public function test_the_digest_emails_unsent_matches_once(): void
    {
        Mail::fake();

        $student = User::factory()->create();
        $monitor = JobMonitor::create([
            'user_id' => $student->id,
            'name' => 'IT',
            'keywords' => ['Programmer'],
            'min_score' => 50,
            'frequency' => 'daily',
        ]);
        $listing = $this->listing(['title' => 'Programmer']);
        app(JobMatcher::class)->matchListing($listing);

        $this->artisan('jobs:digest')->assertSuccessful();
        Mail::assertSent(JobDigestMail::class, fn (JobDigestMail $mail) => $mail->hasTo($student->email) && $mail->total === 1);

        $this->artisan('jobs:digest')->assertSuccessful();
        Mail::assertSentCount(1);

        $this->assertNotNull($monitor->fresh()->last_digest_at);
    }

    public function test_my_jobs_shows_new_matches_then_marks_them_seen(): void
    {
        $student = User::factory()->create();
        $monitor = JobMonitor::create(['user_id' => $student->id, 'name' => 'IT', 'keywords' => ['Programmer'], 'min_score' => 50]);
        app(JobMatcher::class)->matchListing($this->listing(['title' => 'Programmer']));

        $this->actingAs($student)->get('/jobs/mine')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Mine')
            ->has('hits', 1)
            ->where('hits.0.is_new', true));

        $this->assertNotNull($monitor->hits()->sole()->seen_at);
    }

    // --- Admin -----------------------------------------------------------

    public function test_only_admins_reach_the_job_queue(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/jobs')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/jobs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Jobs'));
    }

    public function test_admins_can_add_a_source_and_bad_config_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/admin/jobs/sources', [
            'name' => 'Acme careers',
            'driver' => 'greenhouse',
            'config' => ['board' => 'not a token!'],
        ])->assertUnprocessable()->assertJsonValidationErrors('config.board');

        $this->actingAs($admin)->postJson('/admin/jobs/sources', [
            'name' => 'Gov feed',
            'driver' => 'rss',
            'config' => ['url' => 'https://example.test/feed', 'keywords' => 'IT, programmer'],
        ])->assertCreated();

        $this->assertSame(['IT', 'programmer'], JobSource::query()->sole()->config['keywords']);
    }

    // --- Helpers ---------------------------------------------------------

    private function listing(array $attributes = []): JobListing
    {
        $title = $attributes['title'] ?? 'Programmer';
        $employer = array_key_exists('employer', $attributes) ? $attributes['employer'] : 'Acme';

        return JobListing::create([
            'title' => $title,
            'employer' => $employer,
            'location' => 'Baguio City',
            'employment_type' => 'full-time',
            'apply_url' => 'https://example.test/apply/'.uniqid(),
            'description' => 'Do the work.',
            'salary_text' => 'PHP 30,000 / month',
            'deadline' => now()->addMonth()->toDateString(),
            'status' => 'approved',
            'discovered_at' => now(),
            'fingerprint' => JobFingerprint::for($title, $employer),
            ...$attributes,
        ]);
    }

    private function source(string $driver, array $config, bool $autoApprove = false): JobSource
    {
        return JobSource::create([
            'name' => ucfirst($driver).' test',
            'driver' => $driver,
            'config' => $config,
            'is_active' => true,
            'auto_approve' => $autoApprove,
        ]);
    }

    /** @param list<array{0: string, 1: string, 2: string}> $items */
    private function rss(array $items): string
    {
        $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>Jobs</title>';

        foreach ($items as [$title, $link, $description]) {
            $xml .= '<item><title>'.htmlspecialchars($title).'</title><link>'.$link.'</link><guid>'.$link.'</guid>'
                .'<pubDate>Mon, 10 Nov 2026 08:00:00 +0800</pubDate><description>'.htmlspecialchars($description).'</description></item>';
        }

        return $xml.'</channel></rss>';
    }
}
