<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * Inline TeX between single dollar signs, typeset by KaTeX in the preview.
 */
final class MathInline extends AbstractInline
{
    public function __construct(public readonly string $tex)
    {
        parent::__construct();
    }
}
