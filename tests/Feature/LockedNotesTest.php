<?php

declare(strict_types=1);

use App\Actions\DeleteNote;
use App\Actions\FindUnlinkedMentions;
use App\Actions\IndexVault;
use App\Actions\LinkMention;
use App\Actions\LockNote;
use App\Actions\MergeNotes;
use App\Actions\RemoveNoteLock;
use App\Enums\VersionSource;
use App\Models\DeletedNote;
use App\Models\Note;
use App\Models\PendingLinkRewrite;
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

    $response = $this->postJson(route('vault-passphrase.store', $vault), ['passphrase' => 'correct horse', 'passphrase_confirmation' => 'correct horse'])
        ->assertCreated();
    $key = $response->json('key');

    expect($this->locks->unlockWithRecoveryKey($vault, $response->json('recovery_key')))->toBe(base64_decode((string) $key, true));

    $this->postJson(route('vault-passphrase.store', $vault), ['passphrase' => 'another one', 'passphrase_confirmation' => 'another one'])
        ->assertJsonValidationErrors(['passphrase' => 'This vault already has a passphrase.']);

    $this->postJson(route('vault-key.store', $vault), ['passphrase' => 'correct horse'])->assertExactJson(['key' => $key]);
    $this->postJson(route('vault-key.store', $vault), ['passphrase' => 'wrong horse'])
        ->assertJsonValidationErrors(['passphrase' => 'That passphrase is not right.']);
});

it('changes the passphrase with the current one', function (): void {
    $this->putJson(route('vault-passphrase.update', $this->vault), ['passphrase' => 'staple battery', 'passphrase_confirmation' => 'staple battery'])
        ->assertJsonValidationErrors(['current_passphrase', 'recovery_key']);

    $this->putJson(route('vault-passphrase.update', $this->vault), ['current_passphrase' => 'wrong horse', 'passphrase' => 'staple battery', 'passphrase_confirmation' => 'staple battery'])
        ->assertJsonValidationErrors(['current_passphrase' => 'That passphrase is not right.']);

    $this->putJson(route('vault-passphrase.update', $this->vault), ['current_passphrase' => 'correct horse battery', 'passphrase' => 'short', 'passphrase_confirmation' => 'short'])
        ->assertJsonValidationErrors('passphrase');

    $this->putJson(route('vault-passphrase.update', $this->vault), ['current_passphrase' => 'correct horse battery', 'passphrase' => 'staple battery', 'passphrase_confirmation' => 'staple battery'])
        ->assertOk()
        ->assertExactJson(['key' => base64_encode($this->key), 'recovery_key' => null]);

    expect($this->locks->unlock($this->vault, 'correct horse battery'))->toBeNull()
        ->and($this->locks->unlock($this->vault, 'staple battery'))->toBe($this->key);
});

it('resets a forgotten passphrase with the recovery key, which is then replaced', function (): void {
    lockSecret();
    $recoveryKey = $this->locks->createRecoveryKey($this->vault, $this->key);

    $this->putJson(route('vault-passphrase.update', $this->vault), ['recovery_key' => 'ABCD-EFGH', 'passphrase' => 'staple battery', 'passphrase_confirmation' => 'staple battery'])
        ->assertJsonValidationErrors(['recovery_key' => 'That recovery key is not right.']);

    $response = $this->putJson(route('vault-passphrase.update', $this->vault), ['recovery_key' => $recoveryKey, 'passphrase' => 'staple battery', 'passphrase_confirmation' => 'staple battery'])
        ->assertOk()
        ->assertJsonPath('key', base64_encode($this->key));
    $replacement = (string) $response->json('recovery_key');

    expect($replacement)->not->toBe($recoveryKey)
        ->and($this->locks->unlockWithRecoveryKey($this->vault, $recoveryKey))->toBeNull()
        ->and($this->locks->unlockWithRecoveryKey($this->vault, $replacement))->toBe($this->key)
        ->and($this->locks->unlock($this->vault, 'staple battery'))->toBe($this->key)
        ->and($this->locks->unlockNote((string) file_get_contents($this->vault->path.'/Secret.md'), $this->key))->toBe("# Secret\n\nThe code is swordfish");
});

it('makes a new recovery key when given the passphrase', function (): void {
    $this->postJson(route('vault-recovery-key.store', $this->vault), ['passphrase' => 'wrong horse'])
        ->assertJsonValidationErrors(['passphrase' => 'That passphrase is not right.']);

    $recoveryKey = $this->postJson(route('vault-recovery-key.store', $this->vault), ['passphrase' => 'correct horse battery'])
        ->assertCreated()
        ->json('recovery_key');

    expect($this->locks->unlockWithRecoveryKey($this->vault, (string) $recoveryKey))->toBe($this->key);

    $this->get(route('notes.show', [$this->vault, $this->plain]))
        ->assertInertia(fn ($page) => $page->where('recovery_key_set', true));
});

it('limits passphrase and recovery key guesses per vault', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('vault-key.store', $this->vault), ['passphrase' => 'guess '.$attempt])->assertUnprocessable();
    }

    $this->postJson(route('vault-key.store', $this->vault), ['passphrase' => 'correct horse battery'])->assertTooManyRequests();
    $this->putJson(route('vault-passphrase.update', $this->vault), ['recovery_key' => 'ABCD', 'passphrase' => 'staple battery', 'passphrase_confirmation' => 'staple battery'])->assertTooManyRequests();
    $this->postJson(route('vault-key.store', vaultWithFiles([])), ['passphrase' => 'anything'])->assertUnprocessable();
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
            ->where('recovery_key_set', false)
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

it('refuses a stale save of a locked note, sending the text on disk unlocked', function (): void {
    lockSecret();
    $opened = hash('xxh128', (string) file_get_contents($this->vault->path.'/Secret.md'));

    file_put_contents($this->vault->path.'/Secret.md', $this->locks->lock('# Secret from elsewhere', $this->key));

    ($this->withKey)()->putJson(route('notes.update', [$this->vault, $this->secret]), ['contents' => '# Mine', 'base_hash' => $opened])
        ->assertConflict()
        ->assertJsonPath('note.contents', '# Secret from elsewhere')
        ->assertJsonPath('note.unlocked', true);
});

it('shows a deleted locked note only with the vault key', function (): void {
    lockSecret();

    resolve(DeleteNote::class)->handle($this->secret->fresh());
    $deleted = DeletedNote::query()->sole();

    expect($deleted->locked)->toBeTrue();

    $this->withoutHeader(NoteLocks::HEADER)->getJson(route('deleted-notes.show', [$this->vault, $deleted]))->assertJsonPath('contents', '');
    ($this->withKey)()->getJson(route('deleted-notes.show', [$this->vault, $deleted]))->assertJsonPath('contents', "# Secret\n\nThe code is swordfish");
});

it('rewrites links in locked notes when a note is renamed with the vault unlocked', function (): void {
    file_put_contents($this->vault->path.'/Secret.md', "See [[Plain]], [[Plain#Part|that part]] and [[Other]].\n");
    resolve(IndexVault::class)->handle($this->vault);
    resolve(LockNote::class)->handle($this->secret->refresh(), $this->key);

    ($this->withKey)()->put(route('note-path.update', [$this->vault, $this->plain]), ['path' => 'Archive/Moved'])
        ->assertRedirect();

    $contents = (string) file_get_contents($this->vault->path.'/Secret.md');

    expect($this->locks->isLocked($contents))->toBeTrue()
        ->and($this->locks->unlockNote($contents, $this->key))->toBe("See [[Moved]], [[Moved#Part|that part]] and [[Other]].\n")
        ->and(PendingLinkRewrite::query()->count())->toBe(0);
});

it('rewrites links in locked notes renamed while the vault was locked once it unlocks', function (): void {
    file_put_contents($this->vault->path.'/Secret.md', "See [[Plain]] and [[Plain.md|this]].\n");
    file_put_contents($this->vault->path.'/Broken.md', "# Broken\n");
    resolve(IndexVault::class)->handle($this->vault);
    resolve(LockNote::class)->handle($this->secret->refresh(), $this->key);

    $broken = Note::query()->where('path', 'Broken.md')->sole();
    resolve(LockNote::class)->handle($broken, $this->key);
    $tampered = str_replace('```kite-locked', "```kite-locked\nAAAA", (string) file_get_contents($this->vault->path.'/Broken.md'));
    file_put_contents($this->vault->path.'/Broken.md', $tampered);
    $locked = (string) file_get_contents($this->vault->path.'/Secret.md');

    $this->put(route('note-path.update', [$this->vault, $this->plain]), ['path' => 'Moved'])->assertRedirect();
    $this->put(route('note-path.update', [$this->vault, $this->plain->refresh()]), ['path' => 'Final'])->assertRedirect();

    expect(file_get_contents($this->vault->path.'/Secret.md'))->toBe($locked)
        ->and(PendingLinkRewrite::query()->count())->toBe(2);

    $this->postJson(route('vault-key.store', $this->vault), ['passphrase' => 'correct horse battery'])->assertOk();

    expect($this->locks->unlockNote((string) file_get_contents($this->vault->path.'/Secret.md'), $this->key))->toBe("See [[Final]] and [[Final|this]].\n")
        ->and(file_get_contents($this->vault->path.'/Broken.md'))->toBe($tampered)
        ->and(PendingLinkRewrite::query()->count())->toBe(0);
});

it('only waits to rewrite link keys that led to the renamed note', function (): void {
    $vault = vaultWithFiles([
        'Target.md' => '# Target',
        'Sub/Target.md' => '# Sub target',
        'Secret.md' => "[[Target]] and [[Sub/Target]]\n",
    ]);
    resolve(IndexVault::class)->handle($vault);
    $key = $this->locks->setUp($vault, 'correct horse battery');
    resolve(LockNote::class)->handle(Note::query()->where('vault_id', $vault->id)->where('path', 'Secret.md')->sole(), $key);
    $sub = Note::query()->where('vault_id', $vault->id)->where('path', 'Sub/Target.md')->sole();

    $this->put(route('note-path.update', [$vault, $sub]), ['path' => 'Sub/Other'])->assertRedirect();

    expect(PendingLinkRewrite::query()->sole()->keys)->toBe(['sub/target']);

    $this->withHeader(NoteLocks::HEADER, base64_encode($key))
        ->put(route('folders.update', $vault), ['path' => 'Sub', 'to' => 'Elsewhere'])
        ->assertRedirect();

    expect($this->locks->unlockNote((string) file_get_contents($vault->path.'/Secret.md'), $key))->toBe("[[Target]] and [[Elsewhere/Other]]\n")
        ->and(PendingLinkRewrite::query()->count())->toBe(0);
});

it('applies renames from while the vault was locked before locking another note', function (): void {
    file_put_contents($this->vault->path.'/Secret.md', "[[Plain]]\n");
    file_put_contents($this->vault->path.'/Fresh.md', "[[Plain]]\n");
    resolve(IndexVault::class)->handle($this->vault);
    resolve(LockNote::class)->handle($this->secret->refresh(), $this->key);

    $this->put(route('note-path.update', [$this->vault, $this->plain]), ['path' => 'Moved'])->assertRedirect();
    file_put_contents($this->vault->path.'/Fresh.md', "[[Plain]] is a new note now\n");
    resolve(IndexVault::class)->handle($this->vault);

    resolve(LockNote::class)->handle(Note::query()->where('path', 'Fresh.md')->sole(), $this->key);

    expect($this->locks->unlockNote((string) file_get_contents($this->vault->path.'/Secret.md'), $this->key))->toBe("[[Moved]]\n")
        ->and($this->locks->unlockNote((string) file_get_contents($this->vault->path.'/Fresh.md'), $this->key))->toBe("[[Plain]] is a new note now\n");
});

it('keeps no renames to rewrite for vaults without locked notes', function (): void {
    $this->put(route('note-path.update', [$this->vault, $this->plain]), ['path' => 'Moved'])->assertRedirect();

    expect(PendingLinkRewrite::query()->count())->toBe(0);
});
