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
        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('path_key');
            $table->string('slug');
            $table->string('title');
            $table->json('frontmatter');
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('modified_at')->default(0);
            $table->string('hash', 40);
            $table->timestamps();

            $table->unique(['vault_id', 'path']);
            $table->index(['vault_id', 'slug']);
            $table->index(['vault_id', 'path_key']);
        });

        DB::statement("CREATE VIRTUAL TABLE notes_fts USING fts5(title, body, tokenize = 'porter unicode61 remove_diacritics 2', prefix = '2 3')");

        DB::statement('CREATE TRIGGER notes_fts_delete AFTER DELETE ON notes BEGIN DELETE FROM notes_fts WHERE rowid = old.id; END');
    }
};
