<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->longText('contents');
            $table->string('hash', 40);
            $table->string('source', 16);
            $table->timestamps();

            $table->index(['note_id', 'id']);
        });
    }
};
