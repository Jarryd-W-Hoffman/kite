<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('query', 500)->default('');
            $table->json('columns');
            $table->string('sort_key', 100)->nullable();
            $table->boolean('sort_desc')->default(false);
            $table->timestamps();

            $table->index(['vault_id', 'name']);
        });
    }
};
