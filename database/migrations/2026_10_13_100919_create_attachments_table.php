<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('path_key');
            $table->string('name_key');
            $table->string('extension', 16);
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('modified_at')->default(0);

            $table->unique(['vault_id', 'path']);
            $table->index(['vault_id', 'name_key']);
            $table->index(['vault_id', 'path_key']);
        });
    }
};
