<?php

use App\Enums\DigType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `type` files the dig under one of the four excavation categories, and
     * `published_on` schedules it as a given day's "Today's Dig".
     */
    public function up(): void
    {
        Schema::table('digs', function (Blueprint $table): void {
            $table->string('type')->default(DigType::Emotional->value)->after('title');
            $table->date('published_on')->nullable()->after('status');

            $table->index('published_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digs', function (Blueprint $table): void {
            $table->dropIndex(['published_on']);
            $table->dropColumn(['type', 'published_on']);
        });
    }
};
