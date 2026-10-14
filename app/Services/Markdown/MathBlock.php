<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * Display TeX between $$ lines, typeset by KaTeX in the preview.
 */
final class MathBlock extends AbstractBlock
{
    public string $tex = '';
}
