<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Note;
use App\Models\NoteLink;
use App\Models\NoteTag;
use App\Models\Vault;

it('relates vaults, notes, links and tags', function (): void {
    $vault = Vault::factory()->create();
    $source = Note::factory()->for($vault)->create();
    $target = Note::factory()->for($vault)->create();

    $link = NoteLink::factory()->create([
        'vault_id' => $vault->id,
        'source_note_id' => $source->id,
        'target_note_id' => $target->id,
    ]);

    $tag = NoteTag::factory()->create([
        'vault_id' => $vault->id,
        'note_id' => $source->id,
    ]);

    expect($vault->notes()->count())->toBe(2)
        ->and($vault->links()->sole()->is($link))->toBeTrue()
        ->and($vault->tags()->sole()->is($tag))->toBeTrue()
        ->and($source->vault->is($vault))->toBeTrue()
        ->and($source->outgoingLinks()->sole()->is($link))->toBeTrue()
        ->and($target->backlinks()->sole()->is($link))->toBeTrue()
        ->and($link->source->is($source))->toBeTrue()
        ->and($link->targetNote?->is($target))->toBeTrue()
        ->and($tag->note->is($source))->toBeTrue()
        ->and($source->tags()->sole()->is($tag))->toBeTrue();
});

it('casts note attributes', function (): void {
    $note = Note::factory()->create(['frontmatter' => ['tags' => ['a']]])->refresh();

    expect($note->frontmatter)->toBe(['tags' => ['a']])
        ->and($note->vault_id)->toBeInt();
});

it('relates attachments to their vault', function (): void {
    $attachment = Attachment::factory()->create();

    expect($attachment->vault->attachments()->sole()->is($attachment))->toBeTrue();
});
