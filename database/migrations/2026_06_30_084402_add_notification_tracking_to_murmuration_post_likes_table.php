<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `notified_at` records the one time the post author was alerted about a
     * like. Soft-deleting (rather than removing) a like on unlike keeps that
     * marker, so re-liking after an unlike never re-notifies the author.
     */
    public function up(): void
    {
        Schema::table('murmuration_post_likes', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('user_id');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('murmuration_post_likes', function (Blueprint $table): void {
            $table->dropColumn(['notified_at', 'deleted_at']);
        });
    }
};
