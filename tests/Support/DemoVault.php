<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\IndexAttachments;
use App\Actions\IndexVault;
use App\Enums\GroupColour;
use App\Models\ColourGroup;
use App\Models\SavedSearch;
use App\Models\TableView;
use App\Models\Vault;
use App\Services\CommentStore;
use Illuminate\Support\Facades\File;

/**
 * A small vault that shows off what Kite does, for the user manual's screenshots: projects with
 * properties and tasks, daily notes, callouts, math and a diagram, flashcards, block links and
 * embeds, templates, a canvas, an image, comments, colour groups, a saved search and a view.
 */
final class DemoVault
{
    /**
     * Write the demo notes into the vault's folder, index them and add Kite's own settings.
     */
    public static function fill(Vault $vault): void
    {
        foreach (self::files() as $path => $contents) {
            File::ensureDirectoryExists(dirname($vault->path.'/'.$path));
            File::put($vault->path.'/'.$path, $contents);
        }

        self::writeImage($vault->path.'/Resources/kite-sketch.png');

        resolve(IndexVault::class)->handle($vault);
        resolve(IndexAttachments::class)->handle($vault);

        foreach ([
            ['Projects', 'folder:Projects', GroupColour::Violet],
            ['Learning', 'tag:flashcards', GroupColour::Teal],
            ['Daily', 'folder:Daily', GroupColour::Amber],
        ] as $position => [$name, $query, $colour]) {
            ColourGroup::query()->create(['vault_id' => $vault->id, 'name' => $name, 'query' => $query, 'colour' => $colour, 'position' => $position]);
        }

        SavedSearch::query()->create(['vault_id' => $vault->id, 'name' => 'Active projects', 'query' => 'status:active']);

        TableView::query()->create([
            'vault_id' => $vault->id,
            'name' => 'Projects',
            'query' => 'folder:Projects',
            'columns' => ['status', 'priority', 'due'],
            'sort_key' => 'priority',
            'sort_desc' => false,
            'layout' => 'table',
            'group_by' => 'status',
        ]);

        $comments = resolve(CommentStore::class);
        $comments->add($vault, 'Projects/Kite launch.md', [
            'body' => 'Can we move this a week earlier if the beta goes well?',
            'quote' => 'The launch date is fixed',
            'prefix' => '',
            'suffix' => ' for 18 November.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function files(): array
    {
        return [
            'Home.md' => <<<'MD'
                # Home

                Welcome to my notes. Start with [[Kite launch]], see what's on the [[Roadmap]], or open today's [[2026-10-22|daily note]].

                > [!tip] Getting around
                > Press **Ctrl+K** to search, and **Ctrl+N** for a new note.

                ## This week

                ![[Kite launch#^launch-date]]

                - [ ] Draft the launch post 📅 2026-10-24 #writing
                - [x] Book the venue for the meetup
                MD,
            'Projects/Kite launch.md' => <<<'MD'
                ---
                status: active
                priority: 1
                due: 2026-11-18
                owner: Sam
                tags: [project, launch]
                ---
                # Kite launch

                The launch date is fixed for 18 November. ^launch-date

                ## Goals

                - Ship 1.0 on all three platforms
                - 500 people on the beta list, see [[Pricing ideas]]
                - A short tour on the [[Roadmap]]

                ## Timeline

                ```mermaid
                flowchart LR
                    Beta[Beta] --> Feedback[Feedback round] --> RC[Release candidate] --> Launch((Launch))
                ```

                ## Tasks

                - [x] Pick the launch date
                - [ ] Record the demo video 📅 2026-10-28
                - [ ] Write release notes 📅 2026-11-10
                - [ ] Send the newsletter 📅 2026-11-18

                > [!warning] Before the release candidate
                > Freeze new features one week before.
                MD,
            'Projects/Roadmap.md' => <<<'MD'
                ---
                status: active
                priority: 2
                due: 2027-03-31
                tags: [project]
                ---
                # Roadmap

                What's next after the [[Kite launch]].

                ## Q4

                - Mobile companion, see [[Mobile companion]]
                - Faster sync for big vaults

                ## Q1

                - Plugins, once the core is stable
                MD,
            'Projects/Pricing ideas.md' => <<<'MD'
                ---
                status: waiting
                priority: 3
                tags: [project, idea]
                ---
                # Pricing ideas

                | Plan | Price | For |
                | --- | ---: | --- |
                | Free | $0 | Everyone |
                | Supporter | $5 / month | People who want to help |

                Revenue grows with supporters $s$ as $R = 5s \times 12$ a year.

                Back to [[Kite launch]].
                MD,
            'Areas/Writing.md' => <<<'MD'
                ---
                tags: [area/writing]
                ---
                # Writing

                Notes on writing clearly: short sentences, one idea each. Related: [[Zettelkasten]].
                MD,
            'Resources/Zettelkasten.md' => <<<'MD'
                ---
                tags: [method]
                ---
                # Zettelkasten

                One idea per note, linked to the notes it builds on. Spaced repetition helps keep them in mind: see [[Spaced repetition]].

                Each note links to at least one other, so the [[Roadmap]] of ideas grows by itself.
                MD,
            'Resources/Spaced repetition.md' => <<<'MD'
                # Spaced repetition

                #flashcards/learning

                Reviewing just before you'd forget something makes it stick for longer.

                What is the forgetting curve? :: How memory of something fades over time without review
                Who described the forgetting curve? :: Hermann Ebbinghaus, in 1885
                A good review gap ==grows== each time you remember.
                MD,
            'Resources/Spanish.md' => <<<'MD'
                # Spanish

                #flashcards/spanish

                el perro ::: the dog
                la casa ::: the house
                gracias ::: thank you
                MD,
            'Resources/Graph theory.md' => <<<'MD'
                # Graph theory

                A graph with $n$ notes has at most

                $$
                \frac{n(n-1)}{2}
                $$

                links between different notes. Kite draws yours in the graph view.

                ![[kite-sketch.png]]
                MD,
            'Inbox/Mobile companion.md' => <<<'MD'
                # Mobile companion

                A small app to capture notes on the go. Could share code with [[Plugins]].
                MD,
            'Meetings/2026-10-21 Design review.md' => <<<'MD'
                ---
                tags: [meeting]
                date: 2026-10-21
                ---
                # Design review

                Went through the [[Kite launch]] timeline and the [[Roadmap]].

                - [ ] Sam: update the onboarding copy 📅 2026-10-25
                MD,
            'Daily/2026-10-20.md' => "# Monday 20 October\n\n- Planned the week, see [[Roadmap]].\n",
            'Daily/2026-10-21.md' => "# Tuesday 21 October\n\n- Design review, see [[2026-10-21 Design review]].\n",
            'Daily/2026-10-22.md' => "# Wednesday 22 October\n\n- 09:12 Idea: a tour of the graph view for new users\n- Worked on [[Kite launch]].\n",
            'Templates/Meeting.md' => "---\ntags: [meeting]\ndate: {{date}}\n---\n# {{title}}\n\n## Attendees\n\n- \n\n## Notes\n\n## Actions\n\n- [ ] \n",
            'Launch plan.canvas' => (string) json_encode([
                'nodes' => [
                    ['id' => 'group', 'type' => 'group', 'x' => -40, 'y' => -60, 'width' => 900, 'height' => 420, 'label' => 'Launch'],
                    ['id' => 'goal', 'type' => 'text', 'x' => 0, 'y' => 0, 'width' => 240, 'height' => 120, 'text' => "## Goal\nShip 1.0 on 18 November", 'color' => '4'],
                    ['id' => 'launch', 'type' => 'file', 'x' => 320, 'y' => -20, 'width' => 260, 'height' => 180, 'file' => 'Projects/Kite launch.md'],
                    ['id' => 'roadmap', 'type' => 'file', 'x' => 320, 'y' => 200, 'width' => 260, 'height' => 120, 'file' => 'Projects/Roadmap.md'],
                    ['id' => 'site', 'type' => 'link', 'x' => 620, 'y' => 40, 'width' => 200, 'height' => 100, 'url' => 'https://jsoncanvas.org'],
                ],
                'edges' => [
                    ['id' => 'e1', 'fromNode' => 'goal', 'fromSide' => 'right', 'toNode' => 'launch', 'toSide' => 'left', 'label' => 'plan'],
                    ['id' => 'e2', 'fromNode' => 'launch', 'fromSide' => 'bottom', 'toNode' => 'roadmap', 'toSide' => 'top', 'label' => 'then'],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * A small sketch of a kite, so notes can show an image attachment.
     */
    private static function writeImage(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        $image = imagecreatetruecolor(480, 260);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 238, 242, 255));
        imagefilledpolygon($image, [240, 30, 330, 120, 240, 220, 150, 120], (int) imagecolorallocate($image, 124, 92, 246));
        imageline($image, 240, 30, 240, 220, (int) imagecolorallocate($image, 255, 255, 255));
        imageline($image, 150, 120, 330, 120, (int) imagecolorallocate($image, 255, 255, 255));
        imagesetthickness($image, 2);
        imageline($image, 240, 220, 200, 255, (int) imagecolorallocate($image, 71, 85, 105));
        imagepng($image, $path);
    }
}
