<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('text', 1000);
            $table->boolean('done')->default(false);
            $table->date('due')->nullable();

            $table->index(['vault_id', 'done']);
            $table->index('note_id');
        });

        // Notes indexed before tasks existed are re-read on the next index, which fills this table.
        DB::table('notes')->update(['modified_at' => 0]);
    }
};
