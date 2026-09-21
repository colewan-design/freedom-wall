<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_reply_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_reply_id')->constrained()->cascadeOnDelete();
            $table->string('voter_token', 64);

            // +1 or -1. Kept as a signed value so a reply's score is one SUM
            // rather than two counts that have to be subtracted.
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['thread_reply_id', 'voter_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_reply_votes');
    }
};
