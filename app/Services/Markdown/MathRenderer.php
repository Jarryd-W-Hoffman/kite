<?php

declare(strict_types=1);

namespace App\Services\Markdown;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

/**
 * Math is written out as escaped TeX in a marked element; the preview typesets it with KaTeX,
 * and anywhere without KaTeX still shows the formula's source.
 */
final readonly class MathRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        return match (true) {
            $node instanceof MathInline => new HtmlElement('span', ['class' => 'math math-inline'], e($node->tex)),
            $node instanceof MathBlock => new HtmlElement('div', ['class' => 'math math-block'], e($node->tex)),
            default => throw new InvalidArgumentException('Expected math.'),
        };
    }
}
