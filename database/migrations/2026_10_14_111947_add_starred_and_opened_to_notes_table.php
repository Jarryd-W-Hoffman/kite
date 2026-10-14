<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->timestamp('starred_at')->nullable();
            $table->timestamp('opened_at')->nullable();

            $table->index(['vault_id', 'starred_at']);
            $table->index(['vault_id', 'opened_at']);
        });
    }
};
