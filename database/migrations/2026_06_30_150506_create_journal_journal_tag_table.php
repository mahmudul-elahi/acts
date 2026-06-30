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
        Schema::create('journal_journal_tag', function (Blueprint $table): void {
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['journal_id', 'journal_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_journal_tag');
    }
};
