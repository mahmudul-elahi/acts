<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Composite indexes that back the cursor-paginated feeds: posts are listed
     * with `WHERE status = 1 ORDER BY created_at DESC, id DESC`, and a post's
     * top-level comments with `WHERE murmuration_post_id = ? AND parent_id IS
     * NULL ORDER BY created_at DESC, id DESC`. Including the `id` tiebreaker
     * keeps the cursor ordering stable.
     */
    public function up(): void
    {
        Schema::table('murmuration_posts', function (Blueprint $table): void {
            $table->index(['status', 'created_at', 'id'], 'murmuration_posts_feed_index');
        });

        Schema::table('murmuration_comments', function (Blueprint $table): void {
            $table->index(['murmuration_post_id', 'parent_id', 'created_at', 'id'], 'murmuration_comments_thread_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('murmuration_posts', function (Blueprint $table): void {
            $table->dropIndex('murmuration_posts_feed_index');
        });

        Schema::table('murmuration_comments', function (Blueprint $table): void {
            $table->dropIndex('murmuration_comments_thread_index');
        });
    }
};
