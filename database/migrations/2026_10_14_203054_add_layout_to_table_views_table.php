<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_views', function (Blueprint $table): void {
            $table->string('layout', 10)->default('table');
            $table->string('group_by', 100)->nullable();
        });
    }
};
