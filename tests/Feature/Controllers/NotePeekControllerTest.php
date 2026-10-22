<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\Vault;
use App\Services\NoteLocks;

function peekVault(array $files): Vault
{
    $vault = vaultWithFiles($files);
    resolve(IndexVault::class)->handle($vault);

    return $vault;
}

it('previews a whole note without its frontmatter', function (): void {
    $vault = peekVault(['Guide.md' => "---\nstatus: draft\n---\n# Guide\n\nRead **this**."]);

    $this->getJson(route('note-peeks.show', [$vault, Note::query()->sole()]))
        ->assertOk()
        ->assertJsonPath('title', 'Guide')
        ->assertJsonPath('path', 'Guide.md')
        ->assertJsonPath('locked', false)
        ->assertJsonPath('truncated', false)
        ->assertJsonPath('html', "<h1 id=\"h-guide\">Guide</h1>\n<p>Read <strong>this</strong>.</p>\n");
});

it('previews the heading a link points at, or the whole note when it has gone', function (): void {
    $vault = peekVault(['Guide.md' => "# Guide\n\nIntro.\n\n## Setup steps\n\nInstall it.\n\n## Usage\n\nUse it."]);
    $note = Note::query()->sole();

    $this->getJson(route('note-peeks.show', [$vault, $note, 'section' => 'setup-steps']))
        ->assertJsonPath('title', 'Guide › Setup steps')
        ->assertJsonPath('html', "<h2 id=\"h-setup-steps\">Setup steps</h2>\n<p>Install it.</p>\n");

    $this->getJson(route('note-peeks.show', [$vault, $note, 'section' => 'Gone']))
        ->assertJsonPath('title', 'Guide')
        ->assertSee('Intro.');
});

it('cuts a long note short at a line break', function (): void {
    $vault = peekVault(['Long.md' => implode("\n\n", array_fill(0, 300, 'A line of the note that goes on.'))]);

    $response = $this->getJson(route('note-peeks.show', [$vault, Note::query()->sole()]))
        ->assertJsonPath('truncated', true);

    expect(mb_substr_count((string) $response->json('html'), '<p>'))->toBeLessThan(300)
        ->and($response->json('html'))->toEndWith("<p>A line of the note that goes on.</p>\n");
});

it('shows nothing of a locked note', function (): void {
    $vault = peekVault(['Secret.md' => 'swordfish']);
    $locks = resolve(NoteLocks::class);
    file_put_contents($vault->path.'/Secret.md', $locks->lock('swordfish', $locks->setUp($vault, 'correct horse')));

    $this->getJson(route('note-peeks.show', [$vault, Note::query()->sole()]))
        ->assertJsonPath('locked', true)
        ->assertJsonPath('html', '')
        ->assertDontSee('swordfish');
});

it('keeps vaults apart', function (): void {
    $vault = peekVault(['A.md' => 'a']);

    $this->getJson(route('note-peeks.show', [Vault::factory()->create(), Note::query()->sole()]))->assertNotFound();
});

it('previews just the block a link points at', function (): void {
    $vault = peekVault(['Guide.md' => "# Guide\n\nBefore.\n\nThe key point. ^key\n\nAfter."]);

    $this->getJson(route('note-peeks.show', [$vault, Note::query()->sole(), 'section' => '^key']))
        ->assertJsonPath('title', 'Guide › ^key')
        ->assertJsonPath('html', "<p>The key point.</p>\n");
});
