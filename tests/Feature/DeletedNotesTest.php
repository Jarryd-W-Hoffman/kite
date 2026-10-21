<?php

declare(strict_types=1);

use App\Actions\ArchiveNote;
use App\Actions\DeleteFolder;
use App\Actions\DeleteNote;
use App\Actions\IndexVault;
use App\Actions\RestoreDeletedNote;
use App\Enums\VersionSource;
use App\Models\DeletedNote;
use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\Vault;
use App\Services\CommentStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function indexedVaultWith(array $files): Vault
{
    $vault = vaultWithFiles($files);
    resolve(IndexVault::class)->handle($vault);

    return $vault;
}

function noteAt(string $path): Note
{
    return Note::query()->where('path', $path)->sole();
}

it('keeps a note deleted in Kite, with its last text, history and star, and restores it', function (): void {
    $vault = indexedVaultWith(['Plans.md' => "# Plans\n\nFirst", 'Links.md' => 'See [[Plans]].']);
    $note = noteAt('Plans.md');
    $note->versions()->create(['contents' => "# Plans\n\nOlder", 'hash' => sha1("# Plans\n\nOlder"), 'source' => VersionSource::Edit]);
    $note->update(['starred_at' => now()]);

    $deleted = resolve(DeleteNote::class)->handle($note->fresh());

    expect($deleted)->toBeInstanceOf(DeletedNote::class)
        ->and($deleted?->path)->toBe('Plans.md')
        ->and($deleted?->title)->toBe('Plans')
        ->and($deleted?->versions()->count())->toBe(2)
        ->and($deleted?->latestVersion()?->contents)->toBe("# Plans\n\nFirst")
        ->and(file_exists($vault->path.'/Plans.md'))->toBeFalse()
        ->and(Note::query()->where('path', 'Plans.md')->exists())->toBeFalse();

    $restored = resolve(RestoreDeletedNote::class)->handle($deleted);

    expect($restored->path)->toBe('Plans.md')
        ->and($restored->starred_at)->not->toBeNull()
        ->and($restored->versions()->count())->toBe(2)
        ->and(file_get_contents($vault->path.'/Plans.md'))->toBe("# Plans\n\nFirst")
        ->and(noteAt('Links.md')->outgoingLinks()->sole()->target_note_id)->toBe($restored->id)
        ->and(DeletedNote::query()->count())->toBe(0)
        ->and(NoteVersion::query()->whereNull('note_id')->count())->toBe(0);
});

it('keeps a note never edited in Kite, from its file', function (): void {
    $vault = indexedVaultWith(['Untouched.md' => 'As written elsewhere']);

    $deleted = resolve(DeleteNote::class)->handle(noteAt('Untouched.md'));

    expect($deleted?->latestVersion()?->contents)->toBe('As written elsewhere')
        ->and($deleted?->latestVersion()?->source)->toBe(VersionSource::External);
});

it('restores beside a note that took its place since', function (): void {
    $vault = indexedVaultWith(['Plans.md' => 'Old plans']);

    $first = resolve(DeleteNote::class)->handle(noteAt('Plans.md'));
    writeVaultFile($vault, 'Plans.md', 'New plans');
    resolve(IndexVault::class)->handle($vault);
    $second = resolve(DeleteNote::class)->handle(noteAt('Plans.md'));
    writeVaultFile($vault, 'Plans.md', 'Newest plans');

    expect(resolve(RestoreDeletedNote::class)->handle($first)->path)->toBe('Plans (restored).md')
        ->and(resolve(RestoreDeletedNote::class)->handle($second)->path)->toBe('Plans (restored 2).md')
        ->and(file_get_contents($vault->path.'/Plans (restored).md'))->toBe('Old plans')
        ->and(file_get_contents($vault->path.'/Plans.md'))->toBe('Newest plans');
});

it('refuses to restore a deleted note with nothing kept', function (): void {
    resolve(RestoreDeletedNote::class)->handle(DeletedNote::factory()->create());
})->throws(InvalidArgumentException::class, 'That deleted note has nothing to restore.');

it('keeps the notes in a deleted folder', function (): void {
    $vault = indexedVaultWith(['Work/A.md' => 'a', 'Work/Deep/B.md' => 'b', 'Other.md' => 'c']);

    resolve(DeleteFolder::class)->handle($vault, 'Work');

    expect(DeletedNote::query()->orderBy('path')->pluck('path')->all())->toBe(['Work/A.md', 'Work/Deep/B.md'])
        ->and(Note::query()->pluck('path')->all())->toBe(['Other.md']);

    resolve(RestoreDeletedNote::class)->handle(DeletedNote::query()->where('path', 'Work/Deep/B.md')->sole());

    expect(file_get_contents($vault->path.'/Work/Deep/B.md'))->toBe('b');
});

it('keeps notes deleted outside Kite that have history, and gives it back if they return', function (): void {
    $vault = indexedVaultWith(['Kept.md' => 'Kept text', 'Plain.md' => 'Plain text']);
    $kept = noteAt('Kept.md');
    $kept->versions()->create(['contents' => 'Kept text', 'hash' => sha1('Kept text'), 'source' => VersionSource::Edit]);
    $kept->update(['starred_at' => now()]);

    $comments = resolve(CommentStore::class);
    File::ensureDirectoryExists(dirname($comments->path($vault, 'Kept.md')));
    File::put($comments->path($vault, 'Kept.md'), '{"format":1,"comments":[]}');

    File::delete([$vault->path.'/Kept.md', $vault->path.'/Plain.md']);

    expect(resolve(IndexVault::class)->handle($vault)['removed'])->toBe(2)
        ->and(DeletedNote::query()->pluck('path')->all())->toBe(['Kept.md']);

    // The same text turns up again later, under another name: a move seen in two syncs.
    writeVaultFile($vault, 'Archive/Kept again.md', 'Kept text');
    writeVaultFile($vault, 'Unrelated.md', 'Kept texT');

    expect(resolve(IndexVault::class)->handle($vault)['added'])->toBe(2);

    $back = noteAt('Archive/Kept again.md');

    expect($back->versions()->count())->toBe(1)
        ->and($back->starred_at)->not->toBeNull()
        ->and(noteAt('Unrelated.md')->versions()->count())->toBe(0)
        ->and(File::exists($comments->path($vault, 'Archive/Kept again.md')))->toBeTrue()
        ->and(DeletedNote::query()->count())->toBe(0);
});

it('forgets deleted notes kept longer than configured', function (): void {
    config(['kite.deleted_notes.keep_days' => 7]);
    $vault = Vault::factory()->create();
    $old = DeletedNote::factory()->for($vault)->create(['created_at' => now()->subDays(8)]);
    NoteVersion::factory()->create(['note_id' => null, 'deleted_note_id' => $old->id]);
    $recent = DeletedNote::factory()->for($vault)->create(['created_at' => now()->subDays(6)]);

    resolve(ArchiveNote::class)->purge($vault->id);

    expect(DeletedNote::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(NoteVersion::query()->count())->toBe(0);
});

it('keeps versions linked to a note or a deleted note, removed with either or the vault', function (): void {
    expect(collect(Schema::getForeignKeys('note_versions'))->mapWithKeys(fn (array $key): array => [$key['foreign_table'] => $key['on_delete']])->all())
        ->toBe(['deleted_notes' => 'cascade', 'notes' => 'cascade']);

    $deleted = DeletedNote::factory()->create();
    NoteVersion::factory()->create(['note_id' => null, 'deleted_note_id' => $deleted->id]);

    $deleted->vault->delete();

    expect(DeletedNote::query()->count())->toBe(0)
        ->and(NoteVersion::query()->count())->toBe(0);
});

it('lists, shows and restores deleted notes', function (): void {
    $vault = indexedVaultWith(['Plans.md' => "# Plans\n\nText"]);

    $this->delete(route('notes.destroy', [$vault, noteAt('Plans.md')]))
        ->assertInertiaFlash('toast.action.label', 'Undo');

    $deleted = DeletedNote::query()->sole();

    $this->getJson(route('deleted-notes.index', $vault))
        ->assertOk()
        ->assertJsonPath('keep_days', 30)
        ->assertJsonPath('deleted_notes.0.id', $deleted->id)
        ->assertJsonPath('deleted_notes.0.title', 'Plans')
        ->assertJsonPath('deleted_notes.0.path', 'Plans.md')
        ->assertJsonPath('deleted_notes.0.versions', 1)
        ->assertJsonPath('deleted_notes.0.locked', false);

    $this->getJson(route('deleted-notes.show', [$vault, $deleted]))
        ->assertOk()
        ->assertJsonPath('contents', "# Plans\n\nText");

    $this->post(route('deleted-note-restores.store', [$vault, $deleted]))
        ->assertRedirectToRoute('notes.show', [$vault, noteAt('Plans.md')])
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Restored “Plans”.']);
});

it('offers undo only when the note was kept, and keeps vaults apart', function (): void {
    $vault = indexedVaultWith(['A.md' => 'a']);
    $other = DeletedNote::factory()->create();

    // Its file is already gone and Kite never kept a version: nothing to bring back.
    File::delete($vault->path.'/A.md');

    $this->delete(route('notes.destroy', [$vault, noteAt('A.md')]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Deleted “A”.']);

    expect(DeletedNote::query()->whereBelongsTo($vault)->count())->toBe(0);

    $this->getJson(route('deleted-notes.show', [$vault, $other]))->assertNotFound();
    $this->post(route('deleted-note-restores.store', [$vault, $other]))->assertNotFound();
});
