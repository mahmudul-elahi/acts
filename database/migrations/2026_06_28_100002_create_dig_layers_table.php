<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dig_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dig_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->text('question');
            $table->string('answer_type');
            $table->unsignedInteger('xp')->default(0);
            $table->boolean('include_other')->default(false);
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->timestamps();

            $table->index(['dig_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dig_layers');
    }
};
