<?php

declare(strict_types=1);

use App\Actions\FindUnlinkedMentions;
use App\Actions\IndexVault;
use App\Actions\LinkMention;
use App\Models\Note;
use App\Models\Vault;
use Illuminate\Support\Facades\File;

function mentionsVault(): Vault
{
    $vault = vaultWithFiles([
        'Projects/Kite App.md' => "---\naliases: [Kite, \"KA\", 5]\n---\n# Kite App\n",
        'Journal.md' => "# Journal\nWorked on the kite app today.\nMore kite stuff.",
        'Linked.md' => 'Already [[Kite App]] linked, also mentions Kite.',
        'Fenced.md' => "```\nKite\n```",
        'Gone.md' => 'Kite was here',
        'Other.md' => 'Nothing relevant',
    ]);

    resolve(IndexVault::class)->handle($vault);

    return $vault;
}

it('derives mention terms from the title, file name and aliases', function (): void {
    $note = Note::factory()->create(['title' => 'Ab', 'path' => 'Folder/Kite App.md', 'frontmatter' => ['alias' => 'Paper kite']]);

    expect(resolve(FindUnlinkedMentions::class)->termsFor($note))->toBe(['Kite App', 'Paper kite']);
});

it('finds notes that mention a note without linking to it', function (): void {
    $vault = mentionsVault();
    $kite = Note::query()->where('path', 'Projects/Kite App.md')->sole();

    File::delete($vault->path.'/Gone.md');

    $mentions = resolve(FindUnlinkedMentions::class)->handle($kite);

    expect($mentions)->toBe([[
        'id' => Note::query()->where('path', 'Journal.md')->value('id'),
        'title' => 'Journal',
        'path' => 'Journal.md',
        'line' => 2,
        'term' => 'kite app',
        'context' => 'Worked on the kite app today.',
    ]])
        ->and(resolve(FindUnlinkedMentions::class)->handle($kite, limit: 0))->toHaveCount(1);
});

it('returns nothing for notes without usable terms', function (): void {
    expect(resolve(FindUnlinkedMentions::class)->handle(Note::factory()->create(['title' => 'A', 'path' => 'B.md'])))->toBe([]);
});

it('links a mention and re-indexes the source note', function (): void {
    $vault = mentionsVault();
    $kite = Note::query()->where('path', 'Projects/Kite App.md')->sole();
    $journal = Note::query()->where('path', 'Journal.md')->sole();

    expect(resolve(LinkMention::class)->handle($journal, $kite, 2))->toBeTrue()
        ->and(file_get_contents($vault->path.'/Journal.md'))->toBe("# Journal\nWorked on the [[kite app]] today.\nMore kite stuff.")
        ->and($kite->backlinks()->where('source_note_id', $journal->id)->exists())->toBeTrue()
        ->and(resolve(LinkMention::class)->handle($journal, $kite, 1))->toBeFalse();
});
