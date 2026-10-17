<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Changelog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints a version's CHANGELOG.md entry for its GitHub release, and fails without one so a
 * version can't be released undocumented.
 */
#[Description("Print a version's changelog entry, for its release notes")]
#[Signature('kite:release-notes {version : The version, e.g. 0.2.0 or v0.2.0}')]
final class ReleaseNotesCommand extends Command
{
    public function handle(Changelog $changelog): int
    {
        $version = $this->argument('version');
        $notes = $changelog->notes($version);

        if ($notes === null) {
            $this->components->error("CHANGELOG.md has no entry for {$version}. Add one before releasing it.");

            return self::FAILURE;
        }

        // Straight to the underlying output: the styled one folds the blank lines Markdown needs.
        $this->output->getOutput()->writeln($notes, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
