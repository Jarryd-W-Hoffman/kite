<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Models\Vault;
use App\Services\DailyNotes;
use App\Services\Templates;
use App\Services\VaultFilesystem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final readonly class OpenDailyNote
{
    public function __construct(
        private VaultFilesystem $files,
        private DailyNotes $daily,
        private IndexNote $indexNote,
        private Templates $templates,
    ) {
        //
    }

    /**
     * Open the daily note for a date, creating it from the template when it does not exist yet.
     */
    public function handle(Vault $vault, ?CarbonInterface $date = null): Note
    {
        $date = CarbonImmutable::instance($date ?? now());
        $path = $this->daily->pathFor($date);

        if (! $this->files->exists($vault, $path)) {
            $this->files->write($vault, $path, $this->contents($vault, $date));
        }

        $note = Note::query()->where('vault_id', $vault->id)->where('path', $path)->first();

        return $note ?? $this->indexNote->handle($vault, $path);
    }

    private function contents(Vault $vault, CarbonImmutable $date): string
    {
        $title = $date->translatedFormat('l, j F Y');
        $template = $this->daily->template();

        try {
            $contents = $template !== null && $this->files->exists($vault, $template)
                ? $this->files->read($vault, $template)
                : "# {{title}}\n\n";
        } catch (InvalidArgumentException) {
            $contents = "# {{title}}\n\n";
        }

        return $this->templates->render($contents, [
            'title' => $title,
            'date' => $date,
            'folder' => dirname($this->daily->pathFor($date)) === '.' ? '' : dirname($this->daily->pathFor($date)),
            'vault' => $vault->name,
        ]);
    }
}
