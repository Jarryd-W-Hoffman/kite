<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('target_note_id')->nullable()->constrained('notes')->nullOnDelete();
            $table->string('target');
            $table->string('target_key');
            $table->string('heading')->nullable();
            $table->string('alias')->nullable();
            $table->boolean('is_embed')->default(false);
            $table->unsignedInteger('line');

            $table->index(['vault_id', 'target_key']);
            $table->index('source_note_id');
            $table->index('target_note_id');
        });
    }
};
