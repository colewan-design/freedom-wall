<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('topic', 30);
            $table->enum('status', ['visible', 'hidden'])->default('visible');
            $table->unsignedInteger('views_count')->default(0);

            // Threads are anonymous, so there is no author to join to. The
            // token identifies the poster's session well enough to mark their
            // own replies and to stop them voting twice; the hashed IP is what
            // moderation has to work with when a session is thrown away.
            $table->string('author_token', 64);
            $table->string('ip_hash');

            // A thread sorts by when it was last talked in, not when it was
            // opened, so a week-old question that just got an answer stays up.
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'last_activity_at']);
            $table->index(['status', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
