<?php

declare(strict_types=1);

use App\Models\NoteVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VACUUM can't run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('note_versions', function (Blueprint $table): void {
            $table->unsignedInteger('size')->default(0);
        });

        // Saving through the model compresses the text and records its size.
        NoteVersion::query()->chunkById(200, function (Collection $versions): void {
            foreach ($versions as $version) {
                $version->update(['contents' => $version->contents, 'updated_at' => $version->updated_at]);
            }
        });

        // Give space back to the disk as versions are pruned, instead of only reusing it.
        DB::statement('PRAGMA auto_vacuum = INCREMENTAL');
        DB::statement('VACUUM');
    }
};
