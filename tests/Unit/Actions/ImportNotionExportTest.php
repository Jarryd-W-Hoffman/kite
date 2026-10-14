<?php

declare(strict_types=1);

use App\Actions\ImportNotionExport;
use App\Models\Note;
use App\Models\TableView;

/**
 * @param  array<string, string>  $files
 */
function notionZip(array $files): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'notion-test');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

const HOME_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const TASKS_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
const ROW_ID = 'cccccccccccccccccccccccccccccccc';

it('imports pages, attachments and databases from a Notion export', function (): void {
    $vault = vaultWithFiles(['Notion/Existing.md' => '# Existing']);
    $inner = notionZip([
        'Home '.HOME_ID.'/Tasks '.TASKS_ID.'.csv' => "\u{FEFF}Name,Status,Done,Points,Estimate,Due,Tags,Owner,Planned,Bad:Column\nShip it,Doing,No,3,1.5,\"October 14, 2026\",\"a, b\",,,x\n",
        'Home '.HOME_ID.'/Tasks '.TASKS_ID.'_all.csv' => "Name,Status\n",
        'Home '.HOME_ID.'/Tasks '.TASKS_ID.'/Ship it '.ROW_ID.'.md' => "# Ship it\n\nStatus: Doing\nDone: No\nPoints: 3\nEstimate: 1.5\nDue: October 14, 2026\nTags: a, b\nOwner: \nPlanned: Smarch 3, 2026\n\nBack [home](../../Home%20".HOME_ID.'.md).',
        'Home '.HOME_ID.'/Tasks '.TASKS_ID.'/Loose '.ROW_ID.'.md' => "# Loose\n\nUnknown: value\n\nText",
        'Home '.HOME_ID.'/Tasks '.TASKS_ID.'/Bare '.ROW_ID.'.md' => 'No title or properties',
    ]);
    $zip = notionZip([
        'Home '.HOME_ID.'.md' => "\u{FEFF}# Home\r\n\r\n".implode("\n", [
            'See [Ship it](Home%20'.HOME_ID.'/Tasks%20'.TASKS_ID.'/Ship%20it%20'.ROW_ID.'.md) and [the plan](Plan%20'.HOME_ID.'.md#Goals).',
            '![Diagram](Home%20'.HOME_ID.'/diagram.png)',
            '[Spec](Home%20'.HOME_ID.'/spec.pdf) and [Tasks](Home%20'.HOME_ID.'/Tasks%20'.TASKS_ID.'.csv) and [](Home%20'.HOME_ID.'/Tasks%20'.TASKS_ID.'.csv)',
            '[Web](https://notion.so) [Top](#top) [Missing](Gone.md) [Escape](../../etc.md)',
            '',
            '<aside>',
            '💡 Remember this',
            'and this',
            '</aside>',
        ]),
        'Plan '.HOME_ID.'.md' => '# Plan',
        'Home '.HOME_ID.'/Plan '.ROW_ID.'.md' => '# Other plan',
        'Home '.HOME_ID.'/diagram.png' => 'PNG',
        'Home '.HOME_ID.'/spec.pdf' => 'PDF',
        'Part-1.zip' => file_get_contents($inner),
        '__MACOSX/._Home.md' => 'junk',
        '.DS_Store' => 'junk',
        '../escape.md' => 'nope',
        'Folder/' => '',
    ]);

    $result = resolve(ImportNotionExport::class)->handle($vault, $zip, 'Notion');

    expect($result)->toBe(['notes' => 6, 'attachments' => 2, 'views' => 1, 'folder' => 'Notion 1'])
        ->and(file_get_contents($vault->path.'/Notion 1/Home.md'))->toBe(implode("\n", [
            '# Home',
            '',
            'See [[Ship it]] and [[Notion 1/Plan|the plan]].',
            '![[Notion 1/Home/diagram.png]]',
            '[Spec](Home/spec.pdf) and Tasks and `Notion 1/Home/Tasks`',
            '[Web](https://notion.so) [Top](#top) [Missing](Gone.md) [Escape](../../etc.md)',
            '',
            '> [!note]',
            '> 💡 Remember this',
            '> and this',
        ]))
        ->and(file_get_contents($vault->path.'/Notion 1/Home/Tasks/Ship it.md'))->toBe(<<<'MD'
            ---
            Status: Doing
            Done: false
            Points: 3
            Estimate: 1.5
            Due: '2026-10-14'
            Tags:
              - a
              - b
            Owner:
            Planned: 'Smarch 3, 2026'
            ---
            # Ship it

            Back [[Home|home]].
            MD)
        ->and(file_get_contents($vault->path.'/Notion 1/Home/Tasks/Bare.md'))->toBe('No title or properties')
        ->and(file_get_contents($vault->path.'/Notion 1/Home/Tasks/Loose.md'))->toBe("# Loose\n\nUnknown: value\n\nText")
        ->and(file_get_contents($vault->path.'/Notion 1/Home/diagram.png'))->toBe('PNG')
        ->and(file_exists($vault->path.'/Notion 1/escape.md'))->toBeFalse()
        ->and(Note::query()->where('path', 'like', 'Notion 1/%')->count())->toBe(6);

    $view = TableView::query()->sole();

    expect($view->name)->toBe('Tasks')
        ->and($view->query)->toBe('folder:"Notion 1/Home/Tasks"')
        ->and($view->columns)->toBe(['Status', 'Done', 'Points', 'Estimate', 'Due', 'Tags', 'Owner', 'Planned']);
});

it('keeps names unique and skips nested zips deeper than one level', function (): void {
    $vault = vaultWithFiles([]);
    $deep = notionZip(['Deep.md' => '# Deep']);
    $inner = notionZip(['Inner.md' => '# Inner', 'More.zip' => file_get_contents($deep)]);
    $zip = notionZip([
        'Same '.HOME_ID.'.md' => '# One',
        'Same '.TASKS_ID.'.md' => '# Two',
        '*** '.ROW_ID.'.md' => '# Starred',
        'Part.zip' => file_get_contents($inner),
    ]);

    $result = resolve(ImportNotionExport::class)->handle($vault, $zip, '  ');

    expect($result['notes'])->toBe(4)
        ->and(file_get_contents($vault->path.'/Notion/Same.md'))->toBe('# One')
        ->and(file_get_contents($vault->path.'/Notion/Same 1.md'))->toBe('# Two')
        ->and(file_exists($vault->path.'/Notion/Untitled.md'))->toBeTrue()
        ->and(file_exists($vault->path.'/Notion/Inner.md'))->toBeTrue()
        ->and(file_exists($vault->path.'/Notion/Deep.md'))->toBeFalse();
});

it('rejects files that are not Notion exports', function (): void {
    $vault = vaultWithFiles([]);
    $notZip = (string) tempnam(sys_get_temp_dir(), 'notion-test');
    file_put_contents($notZip, 'plain text');

    expect(fn () => resolve(ImportNotionExport::class)->handle($vault, $notZip))->toThrow(InvalidArgumentException::class)
        ->and(fn () => resolve(ImportNotionExport::class)->handle($vault, notionZip(['__MACOSX/x' => 'y'])))->toThrow(InvalidArgumentException::class);
});
