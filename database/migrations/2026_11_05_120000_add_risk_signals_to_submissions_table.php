<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // 0-100, summed from the categories in config/moderation.php. Never
            // rejects anything; it only decides what the admin reads first.
            $table->unsignedTinyInteger('risk_score')->default(0);

            // category => the substrings that tripped it, so the dashboard can
            // show why a post floated up without re-running the assessor.
            $table->json('risk_flags')->nullable();

            // Null means "never assessed", which is a different thing from a
            // score of 0 and matters while backfilling old rows.
            $table->timestamp('risk_checked_at')->nullable();

            // Why an admin turned a post down. Nothing reads this yet — it is
            // here to start accumulating labels, because "rejected" on its own
            // lumps bullying in with spam and duplicates and teaches nothing.
            $table->string('rejection_reason')->nullable();

            $table->index(['status', 'risk_score']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'risk_score']);
            $table->dropColumn(['risk_score', 'risk_flags', 'risk_checked_at', 'rejection_reason']);
        });
    }
};
