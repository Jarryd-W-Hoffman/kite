<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaults', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('path')->unique();
            $table->timestamp('opened_at')->nullable()->index();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });
    }
};
