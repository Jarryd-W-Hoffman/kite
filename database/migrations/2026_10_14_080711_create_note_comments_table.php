<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->text('quote');
            $table->string('prefix', 64)->default('');
            $table->string('suffix', 64)->default('');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['note_id', 'created_at']);
        });
    }
};
