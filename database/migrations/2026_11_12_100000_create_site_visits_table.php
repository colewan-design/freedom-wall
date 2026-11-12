<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per visitor, per day, per section of the site. Uniqueness is
     * the table's whole job: "unique visitors today" is a COUNT(DISTINCT)
     * over one day, "all time" over every day, with nothing else to keep
     * in step.
     *
     * The visitor column is a hash of a random first-party cookie. No IP,
     * no account and no user agent is stored, which suits an anonymous wall.
     */
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->date('visited_on');
            $table->string('section', 20);
            $table->char('visitor', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['visited_on', 'section', 'visitor']);
            $table->index(['visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
