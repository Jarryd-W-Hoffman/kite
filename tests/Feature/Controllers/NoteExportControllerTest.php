<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

beforeEach(function (): void {
    $this->vault = vaultWithFiles(['Plan.md' => "# Plan: Q3?\n\nShip it."]);
    resolve(IndexVault::class)->handle($this->vault);
    $this->note = Note::query()->sole();
});

it('downloads a note as a standalone html page', function (): void {
    $response = $this->get(route('note-exports.show', [$this->vault, $this->note, 'html']))
        ->assertOk()
        ->assertDownload('Plan Q3.html')
        ->assertHeader('Content-Type', 'text/html; charset=utf-8');

    expect($response->streamedContent())->toContain('<title>Plan: Q3?</title>')->toContain('<p>Ship it.</p>');
});

it('prints a pdf in the desktop app', function (): void {
    config(['nativephp-internal.running' => true]);
    Http::fake(['*' => ['result' => base64_encode('%PDF-fake')]]);

    $response = $this->withoutMiddleware(PreventRegularBrowserAccess::class)
        ->get(route('note-exports.show', [$this->vault, $this->note, 'pdf']))
        ->assertOk()
        ->assertDownload('Plan Q3.pdf');

    expect($response->streamedContent())->toBe('%PDF-fake');

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), 'print-to-pdf')
        && str_contains(base64_decode((string) $request['html']), 'Ship it.')
        && $request['settings']['printBackground'] === true);
});

it('explains that pdf export needs the desktop app', function (): void {
    $this->fromRoute('notes.show', [$this->vault, $this->note])
        ->get(route('note-exports.show', [$this->vault, $this->note, 'pdf']))
        ->assertSessionHasErrors(['format' => 'PDF export is available in the desktop app.']);
});

it('rejects unknown formats', function (): void {
    $this->get('/vaults/'.$this->vault->id.'/notes/'.$this->note->id.'/export/docx')->assertNotFound();
});
