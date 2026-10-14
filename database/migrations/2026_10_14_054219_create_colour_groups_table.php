<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colour_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('query', 500);
            $table->string('colour', 16);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['vault_id', 'position']);
        });
    }
};
