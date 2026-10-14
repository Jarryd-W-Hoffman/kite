<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Note;
use App\Services\NoteExporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final readonly class PublishNote
{
    public function __construct(private NoteExporter $exporter)
    {
        //
    }

    /**
     * Write a note and the notes it links to as a small static site: the note is index.html and
     * links between the published notes keep working. Returns the folder it was written to.
     */
    public function handle(Note $note): string
    {
        $folder = $this->root().DIRECTORY_SEPARATOR.$this->slug($note->vault->name).DIRECTORY_SEPARATOR.$this->slug($note->title);

        $linked = Note::query()
            ->whereIn('id', $note->outgoingLinks()->whereNotNull('target_note_id')->select('target_note_id'))
            ->whereKeyNot($note->id)
            ->orderBy('title')
            ->get();

        $pages = [$note->id => 'index.html'];

        foreach ($linked as $other) {
            $name = $this->slug($other->title);

            for ($suffix = 2; in_array($name.'.html', $pages, true) || $name === 'index'; $suffix++) {
                $name = $this->slug($other->title).'-'.$suffix;
            }

            $pages[$other->id] = $name.'.html';
        }

        File::ensureDirectoryExists($folder);

        // Only pages from an earlier publish of this note are replaced; nothing else is touched.
        File::delete(File::glob($folder.DIRECTORY_SEPARATOR.'*.html'));

        File::put($folder.DIRECTORY_SEPARATOR.'index.html', $this->exporter->page($note, $pages));

        foreach ($linked as $other) {
            File::put($folder.DIRECTORY_SEPARATOR.$pages[$other->id], $this->exporter->page($other, $pages, 'index.html'));
        }

        return $folder;
    }

    public function root(): string
    {
        $configured = config('kite.publish.folder');

        if (is_string($configured) && mb_trim($configured) !== '') {
            return mb_rtrim(mb_trim($configured), '/\\');
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: storage_path('app');

        return $home.DIRECTORY_SEPARATOR.'Kite Published';
    }

    private function slug(string $text): string
    {
        return Str::slug($text) ?: 'note';
    }
}
