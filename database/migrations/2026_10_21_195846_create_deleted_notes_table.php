<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deleted note keeps its history for a while, so it can be restored: its versions move from
     * the note to a deleted note, and back again on restore.
     */
    public function up(): void
    {
        Schema::create('deleted_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('title');
            // The note's last text as indexed, to recognise it if the file comes back.
            $table->string('hash', 40);
            $table->unsignedBigInteger('size');
            $table->boolean('locked')->default(false);
            $table->timestamp('starred_at')->nullable();
            $table->timestamps();

            $table->index(['vault_id', 'created_at']);
            $table->index(['vault_id', 'size']);
        });

        Schema::table('note_versions', function (Blueprint $table): void {
            $table->foreignId('note_id')->nullable()->change();
            $table->foreignId('deleted_note_id')->nullable()->after('note_id')->constrained()->cascadeOnDelete();

            $table->index(['deleted_note_id', 'id']);
        });
    }
};
