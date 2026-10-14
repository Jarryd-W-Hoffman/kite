<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\RecordNoteVersion;
use App\Actions\SaveNote;
use App\Enums\VersionSource;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Note.md' => 'original']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = Note::query()->sole();
});

function versions(Note $note): array
{
    return $note->versions()->orderBy('id')->get()->map(fn ($version): array => [$version->source->value, $version->contents])->all();
}

it('keeps the original text and folds quick saves into one version', function (): void {
    $save = resolve(SaveNote::class);

    $save->handle($this->note, 'one');
    $save->handle($this->note, 'two');
    $save->handle($this->note, 'two');

    expect(versions($this->note))->toBe([['external', 'original'], ['edit', 'two']]);

    $this->travel(RecordNoteVersion::FOLD_MINUTES + 1)->minutes();
    $save->handle($this->note, 'three');

    expect(versions($this->note))->toBe([['external', 'original'], ['edit', 'two'], ['edit', 'three']]);
});

it('keeps changes made outside kite as their own version', function (): void {
    $save = resolve(SaveNote::class);
    $save->handle($this->note, 'mine');

    file_put_contents($this->vault->path.'/Note.md', 'theirs');
    $save->handle($this->note, 'mine again');

    expect(versions($this->note))->toBe([['external', 'original'], ['edit', 'mine'], ['external', 'theirs'], ['edit', 'mine again']]);
});

it('never folds a restore into an edit', function (): void {
    $save = resolve(SaveNote::class);
    $save->handle($this->note, 'edit');
    $save->handle($this->note, 'original', VersionSource::Restore);
    $save->handle($this->note, 'after');

    expect(versions($this->note))->toBe([['external', 'original'], ['edit', 'edit'], ['restore', 'original'], ['edit', 'after']]);
});

it('records a brand new file without a previous version', function (): void {
    resolve(RecordNoteVersion::class)->handle($this->note, null, 'fresh');

    expect(versions($this->note))->toBe([['edit', 'fresh']]);
});

it('keeps only the newest versions', function (): void {
    $record = resolve(RecordNoteVersion::class);

    foreach (range(1, RecordNoteVersion::KEEP + 5) as $index) {
        $record->handle($this->note, null, "v{$index}", VersionSource::Restore);
    }

    expect($this->note->versions()->count())->toBe(RecordNoteVersion::KEEP)
        ->and($this->note->versions()->oldest('id')->value('contents'))->toBe('v6');
});

it('saves a note whose file disappeared without a previous version', function (): void {
    unlink($this->vault->path.'/Note.md');

    resolve(SaveNote::class)->handle($this->note, 'back again');

    expect(versions($this->note))->toBe([['edit', 'back again']])
        ->and(file_get_contents($this->vault->path.'/Note.md'))->toBe('back again');
});
