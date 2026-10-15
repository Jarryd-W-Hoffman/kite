<?php

declare(strict_types=1);

use App\Actions\FindUnlinkedMentions;
use App\Actions\IndexVault;
use App\Actions\LinkMention;
use App\Actions\LockNote;
use App\Actions\MergeNotes;
use App\Actions\RemoveNoteLock;
use App\Enums\VersionSource;
use App\Models\Note;
use App\Services\CommentStore;
use App\Services\DailyNotes;
use App\Services\NoteLocks;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Secret.md' => "# Secret\n\nThe code is swordfish",
        'Plain.md' => "# Plain\n\nMentions Secret here.\n\n![[Secret]]",
    ]);
    resolve(IndexVault::class)->handle($this->vault);

    $this->locks = resolve(NoteLocks::class);
    $this->key = $this->locks->setUp($this->vault, 'correct horse battery');
    $this->secret = Note::query()->where('path', 'Secret.md')->sole();
    $this->plain = Note::query()->where('path', 'Plain.md')->sole();
    $this->withKey = fn () => $this->withHeader(NoteLocks::HEADER, base64_encode($this->key));
});

function lockSecret(): Note
{
    test()->secret->versions()->create(['contents' => "# Secret\n\nThe code is swordfish", 'hash' => 'x', 'source' => VersionSource::Edit]);
    resolve(CommentStore::class)->add(test()->vault, 'Secret.md', ['body' => 'Hmm', 'quote' => 'swordfish', 'prefix' => '', 'suffix' => '']);

    return resolve(LockNote::class)->handle(test()->secret, test()->key);
}

it('sets up a passphrase and unlocks the vault with it', function (): void {
    $vault = vaultWithFiles([]);

    $this->postJson(route('vault-passphrase.store', $vault), ['passphrase' => 'correct horse', 'passphrase_confirmation' => 'different'])
        ->assertJsonValidationErrors('passphrase');

    $key = $this->postJson(route('vault-passphrase.store', $vault), ['passphrase' => 'correct horse', 'passphrase_confirmation' => 'correct horse'])
        ->assertCreated()
        ->json('key');

    $this->postJson(route('vault-passphrase.store', $vault), ['passphrase' => 'another one', 'passphrase_confirmation' => 'another one'])
        ->assertJsonValidationErrors(['passphrase' => 'This vault already has a passphrase.']);

    $this->postJson(route('vault-key.store', $vault), ['passphrase' => 'correct horse'])->assertExactJson(['key' => $key]);
    $this->postJson(route('vault-key.store', $vault), ['passphrase' => 'wrong horse'])
        ->assertJsonValidationErrors(['passphrase' => 'That passphrase is not right.']);
});

it('locks a note, dropping its plain history and comments', function (): void {
    $locked = lockSecret();
    $contents = (string) file_get_contents($this->vault->path.'/Secret.md');

    expect($locked->locked)->toBeTrue()
        ->and($contents)->not->toContain('swordfish')
        ->and($this->locks->unlockNote($contents, $this->key))->toBe("# Secret\n\nThe code is swordfish")
        ->and($this->secret->versions()->pluck('contents')->all())->toBe([$contents])
        ->and(resolve(CommentStore::class)->all($this->vault, 'Secret.md'))->toBe([])
        ->and(fn () => resolve(LockNote::class)->handle($this->secret, $this->key))->toThrow(InvalidArgumentException::class);
});

it('locks and unlocks notes over http with the vault key', function (): void {
    $this->put(route('note-lock.update', [$this->vault, $this->secret]))
        ->assertSessionHasErrors(['passphrase' => 'Unlock the vault first.']);

    ($this->withKey)()->put(route('note-lock.update', [$this->vault, $this->secret]))
        ->assertRedirectToRoute('notes.show', [$this->vault, $this->secret])
        ->assertInertiaFlash('toast.message', 'Locked “Secret”.');

    ($this->withKey)()->put(route('note-lock.update', [$this->vault, $this->secret]))
        ->assertSessionHasErrors(['note' => 'That note is already locked.']);

    ($this->withKey)()->delete(route('note-lock.destroy', [$this->vault, $this->secret]))
        ->assertRedirectToRoute('notes.show', [$this->vault, $this->secret])
        ->assertInertiaFlash('toast.message', 'Removed the lock from “Secret”.');

    expect(file_get_contents($this->vault->path.'/Secret.md'))->toBe("# Secret\n\nThe code is swordfish")
        ->and($this->secret->refresh()->locked)->toBeFalse();

    ($this->withKey)()->delete(route('note-lock.destroy', [$this->vault, $this->secret]))
        ->assertSessionHasErrors(['note' => 'That note could not be unlocked.']);
    expect(fn () => resolve(RemoveNoteLock::class)->handle($this->secret, $this->key))->toThrow(InvalidArgumentException::class);
});

it('opens and saves a locked note only with the key', function (): void {
    lockSecret();

    $this->get(route('notes.show', [$this->vault, $this->secret]))
        ->assertInertia(fn ($page) => $page
            ->where('note.locked', true)
            ->where('note.unlocked', false)
            ->where('note.contents', '')
            ->where('passphrase_set', true)
            ->where('tree.1.locked', true));

    ($this->withKey)()->get(route('notes.show', [$this->vault, $this->secret]))
        ->assertInertia(fn ($page) => $page
            ->where('note.unlocked', true)
            ->where('note.contents', "# Secret\n\nThe code is swordfish")
            ->where('note.outline.0.text', 'Secret'));

    ($this->withKey)()->getJson(route('note-panes.show', [$this->vault, $this->secret]))
        ->assertJsonPath('note.contents', "# Secret\n\nThe code is swordfish");

    $this->withoutHeader(NoteLocks::HEADER)->putJson(route('notes.update', [$this->vault, $this->secret]), ['contents' => '# Secret v2'])
        ->assertJsonValidationErrors('passphrase');

    ($this->withKey)()->putJson(route('notes.update', [$this->vault, $this->secret]), ['contents' => "# Secret v2\n\nmarlin"])
        ->assertOk()
        ->assertJsonPath('title', 'Secret');

    $contents = (string) file_get_contents($this->vault->path.'/Secret.md');

    expect($contents)->not->toContain('marlin')
        ->and($this->locks->unlockNote($contents, $this->key))->toBe("# Secret v2\n\nmarlin");
});

it('opens locked versions with the key', function (): void {
    lockSecret();
    $this->travel(10)->minutes();
    ($this->withKey)()->putJson(route('notes.update', [$this->vault, $this->secret]), ['contents' => '# Secret v2']);
    [$first, $second] = $this->secret->versions()->oldest('id')->get()->all();

    ($this->withKey)()->getJson(route('note-versions.show', [$this->vault, $this->secret, $second]))
        ->assertJson(['contents' => '# Secret v2', 'previous' => "# Secret\n\nThe code is swordfish", 'locked' => true]);

    ($this->withKey)()->getJson(route('note-versions.show', [$this->vault, $this->secret, $first]))
        ->assertJson(['contents' => "# Secret\n\nThe code is swordfish", 'previous' => null]);

    $this->withoutHeader(NoteLocks::HEADER)->getJson(route('note-versions.show', [$this->vault, $this->secret, $first]))
        ->assertJson(['contents' => '', 'previous' => null, 'locked' => true]);
});

it('keeps locked notes out of everything that would show or change their text', function (): void {
    lockSecret();
    $vault = $this->vault;

    $this->get(route('notes.show', [$vault, $this->plain]))
        ->assertInertia(fn ($page) => $page->where('note.html', fn (string $html): bool => str_contains($html, 'This note is locked.') && ! str_contains($html, 'swordfish')));

    expect(resolve(FindUnlinkedMentions::class)->handle($this->plain))->toBe([])
        ->and(resolve(LinkMention::class)->handle($this->secret->refresh(), $this->plain, 3))->toBeFalse();

    $this->put(route('note-frontmatter.update', [$vault, $this->secret]), ['key' => 'status', 'value' => 'x'])
        ->assertSessionHasErrors(['key' => '“Secret” is locked. Open it to change its properties.']);
    $this->postJson(route('note-comments.store', [$vault, $this->secret]), ['body' => 'Hi', 'quote' => 'x'])
        ->assertJsonValidationErrors(['body' => 'Locked notes can’t have comments.']);
    $this->get(route('note-exports.show', [$vault, $this->secret, 'html']))
        ->assertSessionHasErrors(['format' => 'Locked notes can’t be exported.']);
    $this->post(route('note-publications.store', [$vault, $this->secret]))
        ->assertSessionHasErrors(['note' => 'Locked notes can’t be published.']);
    $this->post(route('note-merges.store', [$vault, $this->plain]), ['into' => $this->secret->id])
        ->assertSessionHasErrors(['into' => 'Locked notes can’t be merged. Remove the lock first.']);

    expect(fn () => resolve(MergeNotes::class)->handle($this->secret->refresh(), $this->plain))->toThrow(InvalidArgumentException::class);
});

it('extracts from a locked note into another locked note', function (): void {
    lockSecret();

    $this->postJson(route('note-extractions.store', [$this->vault, $this->secret]), ['title' => 'Part', 'text' => 'swordfish'])
        ->assertJsonValidationErrors(['text' => 'Unlock the vault to extract from a locked note.']);

    ($this->withKey)()->postJson(route('note-extractions.store', [$this->vault, $this->secret]), ['title' => 'Part', 'text' => 'swordfish'])
        ->assertCreated();

    $contents = (string) file_get_contents($this->vault->path.'/Part.md');

    expect($this->locks->unlockNote($contents, $this->key))->toBe("# Part\n\nswordfish\n")
        ->and(Note::query()->where('path', 'Part.md')->sole()->locked)->toBeTrue();
});

it('refuses captures into a locked daily note', function (): void {
    $path = resolve(DailyNotes::class)->pathFor(now());
    @mkdir(dirname($this->vault->path.'/'.$path), recursive: true);
    file_put_contents($this->vault->path.'/'.$path, $this->locks->lock('# Today', $this->key));
    resolve(IndexVault::class)->handle($this->vault);
    $this->vault->update(['opened_at' => now()]);

    $this->post(route('capture.store'), ['text' => 'Buy string'])
        ->assertSessionHasErrors(['text' => 'Today’s note is locked, so captures can’t be added to it.']);
});
