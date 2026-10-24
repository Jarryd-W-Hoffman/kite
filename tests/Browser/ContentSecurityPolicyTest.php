<?php

declare(strict_types=1);

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Models\Note;
use App\Services\Canvases;
use Illuminate\Support\Facades\Vite;

beforeEach(function (): void {
    // Check the built assets, as the app ships them, even while a Vite dev server is running.
    Vite::useHotFile(storage_path('framework/testing/no-hot'));

    $this->vault = vaultWithFiles([
        'Home.md' => "# Home\n\nSee [[Ideas]]. #project\n\n\$e^{i\\pi} + 1 = 0\$\n\n```mermaid\ngraph TD\n  A --> B\n```\n\n![[dot.png]]\n\n![[Ideas]]",
        'Ideas.md' => "# Ideas\n\nBack to [[Home]]",
        'Spanish.md' => "# Spanish #flashcards/spanish\n\nperro :: dog",
        'dot.png' => (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true),
    ]);
    resolve(IndexAttachments::class)->handle($this->vault);
    resolve(IndexVault::class)->handle($this->vault);
    resolve(Canvases::class)->write($this->vault, 'Board.canvas', [['id' => 'a', 'type' => 'text', 'text' => '**Hi**', 'x' => 0, 'y' => 0, 'width' => 200, 'height' => 80]], []);

    $this->home = Note::query()->where('path', 'Home.md')->sole();
});

it('runs every page within its content security policy', function (): void {
    visit(route('notes.show', [$this->vault, $this->home]))
        ->click('[aria-label=Preview]')
        ->assertVisible('.katex')
        ->assertVisible('@mermaid-diagram')
        ->assertScript("document.querySelector('img.attachment-embed')?.complete && document.querySelector('img.attachment-embed').naturalWidth === 1", true)
        ->assertNoConsoleLogs();

    visit(route('vault-graph.show', $this->vault))->assertSee('Graph')->assertNoConsoleLogs();
    visit(route('canvases.show', [$this->vault, 'Board.canvas']))->assertSee('Hi')->assertNoConsoleLogs();
    visit(route('flashcards.index', [$this->vault, 'study' => 1]))->click('@show-answer')->assertSee('dog')->assertNoConsoleLogs();
    visit(route('tasks.index', $this->vault))->assertNoConsoleLogs();
    visit(route('welcome.show', [$this->vault, 'updated' => 1]))->assertSee('Recent notes')->assertNoConsoleLogs();
    visit(route('vaults.index'))->assertNoConsoleLogs();
});

it('refuses scripts that find their way into the page', function (): void {
    visit(route('notes.show', [$this->vault, $this->home]))
        ->assertSee('Home')
        ->assertScript(<<<'JS'
            new Promise((resolve) => {
                document.addEventListener('securitypolicyviolation', (event) => resolve(event.violatedDirective), { once: true });
                const script = document.createElement('script');
                script.textContent = 'window.injected = true';
                document.body.append(script);
                setTimeout(() => resolve(window.injected === true ? 'ran' : 'no violation'), 1000);
            })
            JS, 'script-src-elem');
});
