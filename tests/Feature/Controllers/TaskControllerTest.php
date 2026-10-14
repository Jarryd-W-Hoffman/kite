<?php

declare(strict_types=1);

use App\Actions\IndexVault;
use App\Models\Note;

beforeEach(function (): void {
    $this->vault = vaultWithFiles([
        'Work/Plan.md' => "# Plan #work\n\n- [ ] Ship it due:2026-10-20\n- [x] Draft",
        'Home.md' => "# Home\n\n- [ ] Call mum\n- [ ] Pay rent due:2026-10-01",
    ]);
    resolve(IndexVault::class)->handle($this->vault);
});

it('lists open tasks first by due date, then done ones', function (): void {
    $this->get(route('tasks.index', $this->vault))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/index')
            ->where('open_tasks', 3)
            ->where('tasks.0.text', 'Pay rent due:2026-10-01')
            ->where('tasks.0.due', '2026-10-01')
            ->where('tasks.1.text', 'Ship it due:2026-10-20')
            ->where('tasks.1.tags', ['work'])
            ->where('tasks.1.line', 3)
            ->where('tasks.2.text', 'Call mum')
            ->where('tasks.3.text', 'Draft')
            ->where('tasks.3.done', true));
});

it('ticks and unticks a task in its note', function (): void {
    $note = Note::query()->where('path', 'Work/Plan.md')->sole();

    $this->fromRoute('tasks.index', $this->vault)
        ->put(route('note-tasks.update', [$this->vault, $note, 3]), ['text' => 'Ship it due:2026-10-20', 'done' => true])
        ->assertRedirectToRoute('tasks.index', $this->vault);

    $this->put(route('note-tasks.update', [$this->vault, $note, 4]), ['text' => 'Draft', 'done' => false]);

    expect(file_get_contents($this->vault->path.'/Work/Plan.md'))->toBe("# Plan #work\n\n- [x] Ship it due:2026-10-20\n- [ ] Draft")
        ->and($this->get(route('tasks.index', $this->vault))->inertiaProps('open_tasks'))->toBe(3);
});

it('refuses to tick a line that no longer holds the task', function (int $line, string $text): void {
    $note = Note::query()->where('path', 'Home.md')->sole();

    $this->fromRoute('tasks.index', $this->vault)
        ->put(route('note-tasks.update', [$this->vault, $note, $line]), ['text' => $text, 'done' => true])
        ->assertSessionHasErrors(['task' => 'That task has changed since the list was loaded.']);

    expect(file_get_contents($this->vault->path.'/Home.md'))->toContain('- [ ] Call mum');
})->with([
    'different text' => [3, 'Call dad'],
    'not a task' => [1, '# Home'],
    'past the end' => [40, 'Call mum'],
]);
