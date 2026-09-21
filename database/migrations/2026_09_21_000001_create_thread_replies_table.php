<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('thread_replies')->cascadeOnDelete();

            // Stored rather than walked up the chain: the tree is read on every
            // page load and written once, and the indent cap has to be checked
            // before a reply is accepted.
            $table->unsignedTinyInteger('depth')->default(0);

            $table->text('body');
            $table->enum('status', ['visible', 'hidden'])->default('visible');
            $table->string('author_token', 64);
            $table->string('ip_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_replies');
    }
};
