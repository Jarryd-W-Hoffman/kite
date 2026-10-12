<?php

declare(strict_types=1);

use App\Actions\ResolveLinks;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\Vault;

it('resolves dangling links by name and by path', function (): void {
    $vault = Vault::factory()->create();
    $other = Vault::factory()->create();

    $source = Note::factory()->for($vault)->create();
    $byName = Note::factory()->for($vault)->create(['path' => 'Deep/Topic.md', 'path_key' => 'deep/topic', 'slug' => 'topic']);
    $byPath = Note::factory()->for($vault)->create(['path' => 'A/B/Leaf.md', 'path_key' => 'a/b/leaf', 'slug' => 'leaf']);
    Note::factory()->for($other)->create(['path' => 'Ghost.md', 'path_key' => 'ghost', 'slug' => 'ghost']);

    foreach (['topic', 'b/leaf', 'ghost'] as $line => $key) {
        NoteLink::query()->create([
            'vault_id' => $vault->id,
            'source_note_id' => $source->id,
            'target' => $key,
            'target_key' => $key,
            'line' => $line + 1,
        ]);
    }

    expect(resolve(ResolveLinks::class)->handle($vault))->toBe(2)
        ->and(NoteLink::query()->orderBy('line')->pluck('target_note_id')->all())->toBe([$byName->id, $byPath->id, null]);
});

it('does nothing when there are no dangling links', function (): void {
    expect(resolve(ResolveLinks::class)->handle(Vault::factory()->create()))->toBe(0);
});
