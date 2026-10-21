<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Writes a large, realistic vault for benchmarks and screenshots: daily notes, meetings, projects,
 * areas and reference notes in folders, with frontmatter, tags, tasks and wiki links that
 * favour a few well-linked hub notes, as real vaults do. The same seed always writes the same
 * vault.
 */
final class LargeVault
{
    /** Each note is about one topic, and most of its links stay within it, so topics form clusters. */
    public const array TOPICS = [
        'Software', 'Design', 'Writing', 'Cooking', 'Gardening', 'Psychology', 'Economics', 'Music', 'Travel', 'History',
    ];

    private const array SECTIONS = [
        // Folder, share of notes, kind.
        ['Resources', 40, 'resource'],
        ['Daily', 15, 'daily'],
        ['Meetings', 14, 'meeting'],
        ['Projects', 15, 'project'],
        ['Areas', 6, 'area'],
        ['Inbox', 5, 'inbox'],
        ['Archive', 5, 'resource'],
    ];

    private const array AREAS = ['Health', 'Finance', 'Home', 'Career', 'Family', 'Learning', 'Community', 'Hobbies'];

    private const array ADJECTIVES = [
        'Practical', 'Notes on', 'Thoughts on', 'Patterns for', 'A primer on', 'Questions about', 'Lessons from',
        'Ideas for', 'Reading list on', 'Deep dive into', 'Basics of', 'Mistakes in', 'Tools for', 'Principles of',
    ];

    private const array QUALIFIERS = ['', '', '', ' in practice', ' at scale', ' for beginners', ' revisited', ' in small teams', ' at home'];

    private const array NOUNS = [
        'caching', 'habits', 'colour theory', 'compound interest', 'sourdough', 'spaced repetition', 'indexes',
        'stoicism', 'composition', 'feedback loops', 'negotiation', 'sleep', 'garden beds', 'interviews',
        'estimation', 'event sourcing', 'grammar', 'interval training', 'note taking', 'pricing', 'typefaces',
        'joinery', 'onboarding', 'retrospectives', 'search ranking', 'load balancing', 'memory', 'focus',
        'budgeting', 'travel planning', 'long reads', 'learning curves', 'trade-offs', 'small teams', 'rituals',
    ];

    private const array WORDS = [
        'the', 'a', 'and', 'of', 'to', 'in', 'is', 'that', 'it', 'for', 'with', 'as', 'on', 'this', 'by', 'idea',
        'system', 'note', 'link', 'time', 'work', 'plan', 'question', 'answer', 'pattern', 'change', 'reason',
        'example', 'detail', 'review', 'draft', 'source', 'quote', 'summary', 'problem', 'solution', 'cost',
        'value', 'risk', 'team', 'goal', 'habit', 'practice', 'context', 'decision', 'model', 'signal', 'noise',
        'structure', 'process', 'feedback', 'memory', 'attention', 'quality', 'speed', 'scale', 'kite', 'garden',
        'river', 'budget', 'recipe', 'chapter', 'library', 'interface', 'deadline', 'weekly', 'better', 'simple',
        'clear', 'slow', 'quick', 'small', 'large', 'early', 'later', 'often', 'rarely', 'always', 'maybe',
    ];

    private const array TAGS = [
        'idea', 'todo', 'reference', 'evergreen', 'draft', 'question', 'book', 'article', 'podcast', 'person',
        'meeting', 'decision', 'howto', 'review', 'someday', 'area/work', 'area/home', 'area/health',
        'status/active', 'status/waiting', 'status/done', 'project/kite', 'project/garden', 'project/website',
    ];

    private readonly Randomizer $random;

    /** @var list<string> Titles written so far, in order: links point back at them. */
    private array $titles = [];

    /** @var array<string, list<string>> The same, by topic. */
    private array $titlesByTopic = [];

    /** @var array<string, true> */
    private array $taken = [];

    private function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    /**
     * Write `$notes` notes into the folder at `$path`, returning how many bytes were written.
     */
    public static function write(string $path, int $notes, int $seed = 42): int
    {
        return new self($seed)->writeNotes($path, $notes);
    }

    private function writeNotes(string $path, int $notes): int
    {
        $bytes = 0;
        $plan = [];

        foreach (self::SECTIONS as [$folder, $share, $kind]) {
            $plan[] = [$folder, $kind, intdiv($notes * $share, 100)];
        }

        // Rounding leaves a few notes over; they go to the biggest section.
        $plan[0][2] += $notes - array_sum(array_column($plan, 2));

        // Interleave the sections so links reach across folders, as they do in a vault built up over time.
        $queue = [];

        foreach ($plan as [$folder, $kind, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $queue[] = [$folder, $kind, $i];
            }
        }

        $queue = $this->random->shuffleArray($queue);

        foreach ($queue as [$folder, $kind, $index]) {
            $topic = $this->pick(self::TOPICS);
            [$relativePath, $title] = $this->place($folder, $kind, $index, $topic);
            $contents = $this->contents($kind, $title, $topic);

            File::ensureDirectoryExists(dirname($path.'/'.$relativePath));
            File::put($path.'/'.$relativePath, $contents);

            $this->titles[] = $title;
            $this->titlesByTopic[$topic][] = $title;
            $bytes += mb_strlen($contents, '8bit');
        }

        return $bytes;
    }

    /**
     * @return array{0: string, 1: string} The note's path and title.
     */
    private function place(string $folder, string $kind, int $index, string $topic): array
    {
        [$subfolder, $title] = match ($kind) {
            'daily' => $this->dailyNote($index),
            'meeting' => [(string) (2024 + intdiv($index, 600)), 'Meeting '.$this->date($index).' '.$this->pick(self::NOUNS)],
            'project' => ['', $this->pick(['Launch', 'Redesign', 'Migrate', 'Plan', 'Research', 'Build', 'Write']).' '.$this->pick(self::NOUNS)],
            'area' => [$this->pick(self::AREAS), $this->pick(self::ADJECTIVES).' '.$this->pick(self::NOUNS).$this->pick(self::QUALIFIERS)],
            'inbox' => ['', ucfirst($this->pick(self::NOUNS)).' '.$this->pick(['idea', 'question', 'link', 'thought'])],
            default => [$topic, $this->pick(self::ADJECTIVES).' '.$this->pick(self::NOUNS).$this->pick(self::QUALIFIERS)],
        };

        if ($kind === 'project') {
            $subfolder = ucfirst(explode(' ', $title, 2)[1]);
        }

        $title = $this->unique(ucfirst($title));
        $folderPath = $subfolder === '' ? $folder : $folder.'/'.$subfolder;

        return [$folderPath.'/'.$title.'.md', $title];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dailyNote(int $index): array
    {
        $date = $this->date($index);

        return [mb_substr($date, 0, 4).'/'.mb_substr($date, 5, 2), $date];
    }

    private function date(int $index): string
    {
        return date('Y-m-d', (int) strtotime('2022-01-01 +'.$index.' days'));
    }

    private function unique(string $title): string
    {
        $candidate = $title;

        for ($n = 2; isset($this->taken[mb_strtolower($candidate)]); $n++) {
            $candidate = $title.' '.$n;
        }

        $this->taken[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    private function contents(string $kind, string $title, string $topic): string
    {
        $tags = $this->pickSome(self::TAGS, $this->random->getInt(0, 3));
        $lines = ['---'];

        if ($tags !== []) {
            $lines[] = 'tags: ['.implode(', ', $tags).']';
        }

        if ($kind === 'project') {
            $lines[] = 'status: '.$this->pick(['active', 'waiting', 'done', 'someday']);
            $lines[] = 'priority: '.$this->random->getInt(1, 3);
            $lines[] = 'due: 2026-'.sprintf('%02d-%02d', $this->random->getInt(1, 12), $this->random->getInt(1, 28));
        }

        $lines[] = 'topic: '.$topic;
        $lines[] = 'created: '.$this->date($this->random->getInt(0, 1500));
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '# '.$title;
        $lines[] = '';

        $paragraphs = match ($kind) {
            'daily', 'inbox' => $this->random->getInt(1, 4),
            'meeting' => $this->random->getInt(3, 6),
            default => $this->random->getInt(3, 12),
        };

        for ($p = 0; $p < $paragraphs; $p++) {
            if ($p > 0 && $this->chance(30)) {
                $lines[] = '## '.ucfirst($this->pick(self::NOUNS));
                $lines[] = '';
            }

            $lines[] = $this->paragraph($topic);
            $lines[] = '';

            if ($this->chance(15)) {
                for ($task = $this->random->getInt(1, 4); $task > 0; $task--) {
                    $due = $this->chance(40) ? ' 📅 2026-'.sprintf('%02d-%02d', $this->random->getInt(1, 12), $this->random->getInt(1, 28)) : '';
                    $lines[] = '- ['.($this->chance(40) ? 'x' : ' ').'] '.ucfirst($this->sentence(4, 9)).$due;
                }

                $lines[] = '';
            }

            if ($this->chance(5)) {
                $lines[] = "```php\n\$cache->remember('key', 60, fn () => \$value);\n```";
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function paragraph(string $topic): string
    {
        $sentences = [];

        for ($count = $this->random->getInt(2, 6); $count > 0; $count--) {
            $text = ucfirst($this->sentence(6, 18));

            if ($this->chance(12)) {
                $text .= ' See '.$this->link($topic).'.';
            }

            if ($this->chance(8)) {
                $text .= ' #'.$this->pick(self::TAGS);
            }

            $sentences[] = $text.'.';
        }

        return str_replace('..', '.', implode(' ', $sentences));
    }

    private function sentence(int $min, int $max): string
    {
        return implode(' ', array_map(fn (): string => $this->pick(self::WORDS), range(1, $this->random->getInt($min, $max))));
    }

    /**
     * A wiki link to an earlier note, mostly about the same topic and mostly to the oldest (best
     * linked) ones; a few go anywhere, and some to notes that don't exist yet.
     */
    private function link(string $topic): string
    {
        $titles = $this->chance(75) ? ($this->titlesByTopic[$topic] ?? []) : $this->titles;

        if ($titles === [] || $this->chance(5)) {
            return '[['.ucfirst($this->pick(self::NOUNS)).' someday]]';
        }

        $target = $titles[(int) floor(count($titles) * $this->random->nextFloat() ** 3)];

        return match (true) {
            $this->chance(10) => '[['.$target.'|'.mb_strtolower($target).']]',
            $this->chance(5) => '[['.$target.'#'.ucfirst($this->pick(self::NOUNS)).']]',
            default => '[['.$target.']]',
        };
    }

    /**
     * @phpstan-impure
     */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * @param  list<string>  $items
     */
    private function pick(array $items): string
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function pickSome(array $items, int $count): array
    {
        return array_slice($this->random->shuffleArray($items), 0, $count);
    }
}
