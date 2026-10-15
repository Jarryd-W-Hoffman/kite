<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Casts\CompressedText;
use App\Enums\VersionSource;
use App\Models\Note;
use App\Models\NoteVersion;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Note.md' => 'original']);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = Note::query()->sole();
});

it('stores text compressed and reads it back', function (): void {
    $text = str_repeat("## Heading é\n\nSome text that repeats itself.\n", 200);
    $version = $this->note->versions()->create(['contents' => $text, 'hash' => sha1($text), 'source' => VersionSource::Edit]);
    $stored = (string) DB::table('note_versions')->where('id', $version->id)->value('contents');

    expect($stored)->toStartWith(CompressedText::MARKER)
        ->and(mb_strlen($stored, '8bit'))->toBeLessThan(mb_strlen($text, '8bit') / 10)
        ->and($version->size)->toBe(mb_strlen($text))
        ->and(NoteVersion::query()->find($version->id)?->contents)->toBe($text)
        ->and($this->note->versions()->pluck('contents')->all())->toBe([$text]);
});

it('reads versions saved before compression as they are', function (): void {
    $id = DB::table('note_versions')->insertGetId(['note_id' => $this->note->id, 'contents' => 'plain old text', 'hash' => 'x', 'source' => 'edit', 'created_at' => now(), 'updated_at' => now()]);

    expect(NoteVersion::query()->find($id)?->contents)->toBe('plain old text');
});

it('refuses compressed text that is damaged', function (): void {
    $id = DB::table('note_versions')->insertGetId(['note_id' => $this->note->id, 'contents' => CompressedText::MARKER.'not deflate', 'hash' => 'x', 'source' => 'edit', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => NoteVersion::query()->find($id)?->contents)->toThrow(RuntimeException::class, 'The [contents] text could not be decompressed.');
});

it('lists a note’s history without reading its text', function (): void {
    $this->note->versions()->create(['contents' => 'Hello é', 'hash' => 'x', 'source' => VersionSource::Edit]);

    DB::enableQueryLog();

    $this->getJson(route('note-versions.index', [$this->vault, $this->note]))
        ->assertOk()
        ->assertJsonPath('versions.0.size', 7);

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'note_versions'))->implode(' '))
        ->not->toContain('*')
        ->not->toContain('contents');
});

it('gives freed space back to the disk', function (): void {
    expect(DB::selectOne('PRAGMA auto_vacuum')->auto_vacuum)->toBe(2);
});
