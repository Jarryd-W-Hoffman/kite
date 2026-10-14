<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A note (or one of its sections) rendered inline in place of a standalone ![[embed]].
 */
final class NoteEmbed extends AbstractBlock
{
    public const string RENDERED = 'rendered';

    public const string LOOP = 'loop';

    public const string TOO_DEEP = 'too-deep';

    public const string MISSING_SECTION = 'missing-section';

    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $html,
        public readonly string $state = self::RENDERED,
    ) {
        parent::__construct();
    }
}
