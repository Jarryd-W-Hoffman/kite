<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Titles no longer include inline #tags; re-read every note on the next index to update them.
     */
    public function up(): void
    {
        DB::table('notes')->update(['modified_at' => 0]);
    }
};
