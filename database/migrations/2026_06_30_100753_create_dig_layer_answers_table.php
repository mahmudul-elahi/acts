<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A user's answer to a single dig layer. `xp_awarded` records the XP granted
     * the first time the layer was answered, so re-answering never re-awards.
     * `dig_id` is denormalised for cheap per-dig and total-XP queries.
     */
    public function up(): void
    {
        Schema::create('dig_layer_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dig_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dig_layer_id')->constrained()->cascadeOnDelete();
            $table->string('selected_option')->nullable();
            $table->text('response')->nullable();
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'dig_layer_id']);
            $table->index(['user_id', 'dig_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dig_layer_answers');
    }
};
