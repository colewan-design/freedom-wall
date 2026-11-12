<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JobRadar: job listings gathered from feeds and from people who paste a link,
 * plus the per-student tools on top of them (saved jobs, monitors, matches).
 *
 * One migration for the whole feature so it ships and rolls back as a unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A place the fetcher reads from. Only drivers with a published feed or
        // an official API exist; there is deliberately no "scrape this page".
        Schema::create('job_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('driver', 30);
            $table->json('config');
            $table->boolean('is_active')->default(true);

            // Trusted feeds (an employer's own Greenhouse board) can skip the
            // queue; anything broader lands as pending like a community link.
            $table->boolean('auto_approve')->default(false);

            $table->timestamp('last_fetched_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_found_count')->default(0);
            $table->timestamps();
        });

        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('employer', 160)->nullable();
            $table->string('location', 160)->nullable();
            $table->boolean('is_remote')->default(false);
            $table->string('employment_type', 20)->nullable();
            $table->string('sector', 20)->nullable();
            $table->string('salary_text', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('apply_url', 2048);
            $table->date('deadline')->nullable();

            // When the employer says it went up, if they say, and when we first
            // saw it. The second is always known; the first often is not.
            $table->timestamp('published_at')->nullable();
            $table->timestamp('discovered_at')->useCurrent();

            $table->enum('status', ['pending', 'approved', 'rejected', 'closed'])->default('pending');

            // Normalised title + employer + location. The same vacancy posted
            // on three boards collapses onto one listing through this.
            $table->string('fingerprint', 64);

            // Warning signs found by JobSafety, keyed by kind. Labels, never a
            // verdict: a flagged listing still goes up if a moderator says so.
            $table->json('safety_flags')->nullable();

            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_ip_hash')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('fingerprint');
            $table->index(['status', 'discovered_at']);
        });

        // Every place one listing was seen. Community links have no source row.
        Schema::create('job_listing_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 191)->nullable();
            $table->string('url', 2048);
            $table->string('label', 120);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();

            $table->unique(['job_source_id', 'external_id']);
        });

        // A saved job doubles as the application tracker row.
        Schema::create('job_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('interested');
            $table->text('notes')->nullable();
            $table->date('applied_on')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_listing_id']);
        });

        Schema::create('job_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('keywords');
            $table->string('location', 120)->nullable();
            $table->json('employment_types')->nullable();
            $table->boolean('remote_ok')->default(true);
            $table->unsignedTinyInteger('min_score')->default(50);
            $table->string('frequency', 10)->default('daily');
            $table->boolean('email_alerts')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_digest_at')->nullable();
            $table->timestamps();
        });

        // A listing that cleared a monitor's bar. Doubles as the on-site
        // notification and as the "already emailed" marker for the digest.
        Schema::create('job_monitor_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('reasons');
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['job_monitor_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_monitor_hits');
        Schema::dropIfExists('job_monitors');
        Schema::dropIfExists('job_saves');
        Schema::dropIfExists('job_listing_sources');
        Schema::dropIfExists('job_listings');
        Schema::dropIfExists('job_sources');
    }
};
