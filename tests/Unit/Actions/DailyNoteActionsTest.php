<?php

declare(strict_types=1);

use App\Actions\AppendToDailyNote;
use App\Actions\OpenDailyNote;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:30:00'));
});

it("creates today's note once and reuses it", function (): void {
    $vault = vaultWithFiles();
    $open = resolve(OpenDailyNote::class);

    $note = $open->handle($vault);

    expect($note->path)->toBe('Daily/2026-10-13.md')
        ->and(file_get_contents($vault->path.'/Daily/2026-10-13.md'))->toBe("# Tuesday, 13 October 2026\n\n")
        ->and($open->handle($vault)->id)->toBe($note->id)
        ->and($vault->notes()->count())->toBe(1);
});

it('indexes an existing daily note file that is not indexed yet', function (): void {
    $vault = vaultWithFiles(['Daily/2026-10-12.md' => '# Yesterday']);

    $note = resolve(OpenDailyNote::class)->handle($vault, CarbonImmutable::parse('2026-10-12'));

    expect($note->title)->toBe('Yesterday');
});

it('fills in the template placeholders', function (): void {
    config(['kite.daily.template' => 'Templates/Daily.md']);

    $vault = vaultWithFiles(['Templates/Daily.md' => "# {{title}}\nDate: {{date}} at {{time}}\n"]);

    resolve(OpenDailyNote::class)->handle($vault);

    expect(file_get_contents($vault->path.'/Daily/2026-10-13.md'))->toBe("# Tuesday, 13 October 2026\nDate: 2026-10-13 at 09:30\n");
});

it('falls back to the default contents for missing or unsafe templates', function (string $template): void {
    config(['kite.daily.template' => $template]);

    $vault = vaultWithFiles();

    resolve(OpenDailyNote::class)->handle($vault);

    expect(file_get_contents($vault->path.'/Daily/2026-10-13.md'))->toBe("# Tuesday, 13 October 2026\n\n");
})->with(['Templates/Missing.md', '../outside.md']);

it("appends timestamped entries to today's note", function (): void {
    $vault = vaultWithFiles(['Daily/2026-10-13.md' => '# Today']);
    $append = resolve(AppendToDailyNote::class);

    $append->handle($vault, "  Call Ana\nabout the kite\r\nand [[Graph]]  ");

    $this->travel(5)->minutes();

    $note = $append->handle($vault, 'Second');

    expect(file_get_contents($vault->path.'/Daily/2026-10-13.md'))->toBe("# Today\n- 09:30 Call Ana\n  about the kite\n  and [[Graph]]\n- 09:35 Second\n")
        ->and($note->outgoingLinks()->pluck('target')->all())->toBe(['Graph']);
});
