<?php

declare(strict_types=1);

use App\Actions\BuildGraph;
use App\Actions\IndexVault;
use App\Models\Note;
use App\Models\TableView;
use App\Models\Vault;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\LargeVault;

/*
 * The README promises Kite stays quick in vaults of 10,000 notes and more. This times the work
 * behind what people do most in a vault that size, against budgets with room for a slower CI
 * machine, on a database file like the app's rather than one in memory.
 *
 * Run it with `php artisan test --testsuite=Benchmark`.
 */

const BENCHMARK_NOTES = 10_000;

beforeEach(function (): void {
    $this->folder = storage_path('framework/testing/benchmark');
    $database = $this->folder.'/kite.sqlite';

    File::deleteDirectory($this->folder);
    File::ensureDirectoryExists($this->folder.'/vault');
    File::put($database, '');

    config(['database.connections.sqlite.database' => $database]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $this->bytes = LargeVault::write($this->folder.'/vault', BENCHMARK_NOTES);
    $this->vault = Vault::factory()->create(['name' => 'Benchmark', 'path' => realpath($this->folder.'/vault')]);
});

afterEach(function (): void {
    DB::purge('sqlite');
    File::deleteDirectory($this->folder);
});

/**
 * Run `$work` (the median of `$runs` runs) and record how long it took against its budget.
 *
 * @param  list<array{0: string, 1: float, 2: int}>  $timings
 */
function measureStep(array &$timings, string $step, int $budgetMs, Closure $work, int $runs = 1): mixed
{
    $durations = [];
    $result = null;

    for ($run = 0; $run < $runs; $run++) {
        $start = hrtime(true);
        $result = $work();
        $durations[] = (hrtime(true) - $start) / 1e6;
    }

    sort($durations);

    $timings[] = [$step, $durations[intdiv($runs, 2)], $budgetMs];

    return $result;
}

/**
 * Print the timings as a Markdown table, adding it to the job summary on GitHub Actions.
 *
 * @param  list<array{0: string, 1: float, 2: int}>  $timings
 */
function reportTimings(array $timings, int $bytes): void
{
    $rows = array_map(fn (array $timing): string => sprintf(
        '| %s | %s ms | %s ms | %s |',
        $timing[0],
        number_format($timing[1], $timing[1] < 10 ? 1 : 0),
        number_format($timing[2]),
        $timing[1] <= $timing[2] ? '✅' : '❌',
    ), $timings);

    $table = implode("\n", [
        sprintf('### Large vault: %s notes, %s MB', number_format(BENCHMARK_NOTES), number_format($bytes / 1_048_576, 1)),
        '',
        '| Step | Time | Budget | |',
        '| --- | ---: | ---: | --- |',
        ...$rows,
    ])."\n";

    fwrite(STDERR, "\n".$table);

    $summary = getenv('GITHUB_STEP_SUMMARY');

    if (is_string($summary) && $summary !== '') {
        file_put_contents($summary, $table, FILE_APPEND);
    }
}

it('stays quick in a vault of 10,000 notes', function (): void {
    $vault = $this->vault;
    $timings = [];

    $summary = measureStep($timings, 'Index the whole vault (first open)', 90_000, fn () => resolve(IndexVault::class)->handle($vault));
    expect($summary['added'])->toBe(BENCHMARK_NOTES);

    measureStep($timings, 'Sync, nothing changed (after a file event)', 750, fn () => resolve(IndexVault::class)->handle($vault), runs: 3);

    $note = Note::query()->where('vault_id', $vault->id)->where('path', 'like', 'Projects/%')->orderBy('id')->firstOrFail();
    $hub = Note::query()->where('vault_id', $vault->id)->withCount('backlinks')->orderByDesc('backlinks_count')->firstOrFail();

    measureStep($timings, 'Sync, one note edited outside Kite', 1_000, function () use ($vault, $note): void {
        File::append($vault->path.'/'.$note->path, "\nEdited elsewhere.");
        touch($vault->path.'/'.$note->path, time() + 5);
        resolve(IndexVault::class)->handle($vault);
    });

    measureStep($timings, 'Sync, one note renamed outside Kite', 2_000, function () use ($vault, $note): void {
        File::move($vault->path.'/'.$note->path, $vault->path.'/Inbox/Renamed benchmark note.md');
        expect(resolve(IndexVault::class)->handle($vault)['moved'])->toBe(1);
    });

    foreach ([
        'one word' => 'budget',
        'two words' => 'feedback loops',
        'a prefix' => 'interv',
        'with filters' => 'habit tag:idea folder:Resources',
        'no results' => 'xylophone',
    ] as $label => $query) {
        measureStep($timings, "Search, {$label}", 200, fn () => $this->getJson(route('vault-search.index', [$vault, 'q' => $query]))->assertOk(), runs: 5);
    }

    // Whole pages, as the window first loads them: the sidebar's tree and lists are built too.
    $this->withoutVite();

    measureStep($timings, 'Open a note (sidebar, note, links)', 2_000, fn () => $this->get(route('notes.show', [$vault, $hub]))->assertOk(), runs: 3);

    $edits = 0;
    measureStep($timings, 'Save a note', 250, fn () => $this->putJson(route('notes.update', [$vault, $hub]), ['contents' => "# {$hub->title}\n\nRewritten in Kite, take ".++$edits.'.'])->assertOk(), runs: 3);

    measureStep($timings, 'Open the graph view', 3_500, fn () => $this->get(route('vault-graph.show', $vault))->assertOk(), runs: 3);
    expect(count(resolve(BuildGraph::class)->handle($vault)['nodes']))->toBeGreaterThanOrEqual(BENCHMARK_NOTES);

    measureStep($timings, 'Open the task list', 3_500, fn () => $this->get(route('tasks.index', $vault))->assertOk(), runs: 3);

    $view = TableView::factory()->for($vault)->create(['columns' => ['status', 'tags', 'created'], 'sort_key' => 'created']);
    measureStep($timings, 'Open a table view of every note', 3_500, fn () => $this->get(route('table-views.show', [$vault, $view]))->assertOk(), runs: 3);

    measureStep($timings, 'Open a common tag', 2_000, fn () => $this->get(route('vault-tags.show', [$vault, 'idea']))->assertOk(), runs: 3);
    measureStep($timings, 'Open the flashcards', 2_000, fn () => $this->get(route('flashcards.index', $vault))->assertOk(), runs: 3);
    measureStep($timings, 'Local graph, two links deep', 500, fn () => $this->getJson(route('note-graphs.show', [$vault, $hub, 'depth' => 2]))->assertOk(), runs: 3);
    measureStep($timings, 'Check indexing status (polled)', 100, fn () => $this->getJson(route('vault-status.show', $vault))->assertOk(), runs: 5);

    // Renames rewrite every link that names the note, so the best-linked note is the slowest one.
    measureStep($timings, "Rename the best-linked note ({$hub->backlinks_count} links)", 20_000, fn () => $this->put(route('note-path.update', [$vault, $hub]), ['path' => 'Inbox/Renamed hub.md'])->assertRedirect());

    $folder = 'Resources/Software';
    $notesInFolder = Note::query()->where('vault_id', $vault->id)->where('path', 'like', $folder.'/%')->count();
    measureStep($timings, "Rename a folder ({$notesInFolder} notes)", 25_000, fn () => $this->put(route('folders.update', $vault), ['path' => $folder, 'to' => 'Resources/Programming'])->assertRedirect());
    expect(Note::query()->where('vault_id', $vault->id)->where('path', 'like', 'Resources/Programming/%')->count())->toBe($notesInFolder);

    reportTimings($timings, $this->bytes);

    foreach ($timings as [$step, $milliseconds, $budget]) {
        expect($milliseconds)->toBeLessThanOrEqual($budget, "{$step} took {$milliseconds} ms, over its {$budget} ms budget.");
    }
});
