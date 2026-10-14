<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Actions\MergeNotes;
use App\Enums\VersionSource;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Projects/Plan.md' => "# Plan\n\nIntro\n\nA long passage",
        'Projects/Launch.md' => "---\nstatus: active\n---\n# Launch\n\nGo live. See [[Launch#Go]] and [[Plan]].",
        'Ideas.md' => "---\nstatus: idea\nowner: Sam\n---\n# Ideas\n\n## Later\n\n```sh\n# not a heading\n```\n\nBack to [[Plan]].",
        'Index.md' => "# Index\n\n- [[Ideas|my ideas]]\n- [[Projects/Launch]]",
    ]);

    resolve(IndexVault::class)->handle($this->vault);
});

it('extracts a passage into a new note beside the original', function (): void {
    $vault = $this->vault;
    $plan = Note::query()->where('path', 'Projects/Plan.md')->sole();

    $this->postJson(route('note-extractions.store', [$vault, $plan]), ['title' => ' Passage ', 'text' => "\nA long passage\n"])
        ->assertCreated()
        ->assertJson(['title' => 'Passage', 'link' => 'Passage']);

    $this->postJson(route('note-extractions.store', [$vault, $plan]), ['title' => 'Passage', 'text' => 'Again'])
        ->assertJson(['link' => 'Passage 1']);

    $this->postJson(route('note-extractions.store', [$vault, Note::query()->where('path', 'Ideas.md')->sole()]), ['title' => 'Top', 'text' => 'x'])
        ->assertJson(['link' => 'Top']);

    expect(file_get_contents($vault->path.'/Projects/Passage.md'))->toBe("# Passage\n\nA long passage\n")
        ->and(file_exists($vault->path.'/Top.md'))->toBeTrue();

    $this->postJson(route('note-extractions.store', [$vault, $plan]), ['title' => '', 'text' => ''])
        ->assertJsonValidationErrors(['title', 'text']);
});

it('merges a note into another and points its links there', function (): void {
    $vault = $this->vault;
    $ideas = Note::query()->where('path', 'Ideas.md')->sole();
    $launch = Note::query()->where('path', 'Projects/Launch.md')->sole();

    $this->fromRoute('notes.show', [$vault, $ideas])
        ->post(route('note-merges.store', [$vault, $ideas]), ['into' => $launch->id])
        ->assertRedirectToRoute('notes.show', [$vault, $launch])
        ->assertInertiaFlash('toast.message', 'Merged “Ideas” into this note.');

    expect(file_get_contents($vault->path.'/Projects/Launch.md'))->toBe(<<<'MD'
        ---
        status: active
        owner: Sam
        ---
        # Launch

        Go live. See [[Launch#Go]] and [[Plan]].

        ## Ideas

        ### Later

        ```sh
        # not a heading
        ```

        Back to [[Plan]].

        MD)
        ->and(file_get_contents($vault->path.'/Index.md'))->toBe("# Index\n\n- [[Launch|my ideas]]\n- [[Projects/Launch]]")
        ->and(file_exists($vault->path.'/Ideas.md'))->toBeFalse()
        ->and(Note::query()->whereKey($ideas->id)->exists())->toBeFalse()
        ->and($launch->versions()->latest('id')->first()?->source)->toBe(VersionSource::Merge)
        ->and(Note::query()->where('path', 'Index.md')->sole()->outgoingLinks()->whereNull('target_note_id')->count())->toBe(0);
});

it('merges a note without a heading or properties under its file name', function (): void {
    $vault = vaultWithFiles(['A.md' => "# A\n", 'Loose.md' => "Just text\n\n# Not first\n"]);
    resolve(IndexVault::class)->handle($vault);
    $loose = Note::query()->where('vault_id', $vault->id)->where('path', 'Loose.md')->sole();
    $target = Note::query()->where('vault_id', $vault->id)->where('path', 'A.md')->sole();

    $this->post(route('note-merges.store', [$vault, $loose]), ['into' => $target->id]);

    expect(file_get_contents($vault->path.'/A.md'))->toBe("# A\n\n## Loose\n\nJust text\n\n## Not first\n");
});

it('refuses to merge a note into itself or a note from another vault', function (): void {
    $vault = $this->vault;
    $plan = Note::query()->where('path', 'Projects/Plan.md')->sole();
    $other = vaultWithFiles(['X.md' => '# X']);
    resolve(IndexVault::class)->handle($other);

    $this->post(route('note-merges.store', [$vault, $plan]), ['into' => $plan->id])
        ->assertSessionHasErrors(['into' => 'A note cannot be merged into itself.']);
    $this->post(route('note-merges.store', [$vault, $plan]), ['into' => Note::query()->where('vault_id', $other->id)->sole()->id])
        ->assertSessionHasErrors('into');

    expect(fn () => resolve(MergeNotes::class)->handle($plan, $plan))->toThrow(InvalidArgumentException::class);
});
