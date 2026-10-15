<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\RecordNoteVersion;
use App\Actions\SaveNote;
use App\Enums\VersionSource;
use App\Models\Note;
use Carbon\CarbonInterface;

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

it('thins older history to one version an hour, a day, then a week', function (): void {
    $this->freezeTime();
    $record = resolve(RecordNoteVersion::class);
    $saveAt = function (CarbonInterface $at, string $contents) use ($record): void {
        $this->travelTo($at);
        $record->handle($this->note, null, $contents, VersionSource::Restore);
    };
    $now = now();

    // Restores are never folded, so each of these is its own version.
    $saveAt($now->subWeeks(20)->startOfWeek()->addHour(), 'week a1');
    $saveAt($now->subWeeks(20)->startOfWeek()->addHours(2), 'week a2');
    $saveAt($now->subDays(30)->setTime(9, 0), 'day b1');
    $saveAt($now->subDays(30)->setTime(17, 0), 'day b2');
    $saveAt($now->subDays(3)->setTime(9, 5), 'hour c1');
    $saveAt($now->subDays(3)->setTime(9, 50), 'hour c2');
    $saveAt($now->subDays(3)->setTime(10, 5), 'hour d');
    $saveAt($now->subHours(5), 'recent e1');
    $saveAt($now->subHours(4), 'recent e2');
    $saveAt($now, 'latest');

    expect(array_column(versions($this->note), 1))->toBe(['week a2', 'day b2', 'hour c2', 'hour d', 'recent e1', 'recent e2', 'latest']);
});
