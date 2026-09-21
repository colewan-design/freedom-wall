<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thread_reports', function (Blueprint $table) {
            $table->id();

            // Exactly one of these is set: a report is filed against a thread
            // or against a single reply inside one.
            $table->foreignId('thread_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('thread_reply_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('reason', 40);
            $table->string('reporter_token', 64);
            $table->string('ip_hash');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thread_reports');
    }
};
