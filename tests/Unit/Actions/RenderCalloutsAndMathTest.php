<?php

declare(strict_types=1);

use App\Actions\RenderMarkdown;

function render(string $markdown): string
{
    return resolve(RenderMarkdown::class)->handle(vaultWithFiles(), $markdown);
}

it('turns obsidian callouts into titled boxes', function (): void {
    expect(render("> [!warning] Mind the **gap**\n> Body text\n> more"))
        ->toBe("<blockquote class=\"callout\" data-callout=\"warning\">\n<p class=\"callout-title\">Mind the <strong>gap</strong></p>\n<p>Body text\nmore</p>\n</blockquote>\n");
});

it('titles a callout after its type, keeps fold state and leaves plain quotes alone', function (): void {
    expect(render("> [!tip]-\n> Hidden"))
        ->toBe("<blockquote class=\"callout\" data-callout=\"tip\" data-fold=\"closed\">\n<p class=\"callout-title\">Tip</p>\n<p>Hidden</p>\n</blockquote>\n")
        ->and(render('> [!note]+ Only a title'))
        ->toBe("<blockquote class=\"callout\" data-callout=\"note\" data-fold=\"open\">\n<p class=\"callout-title\">Only a title</p>\n</blockquote>\n")
        ->and(render('> Just a quote'))
        ->toBe("<blockquote>\n<p>Just a quote</p>\n</blockquote>\n");
});

it('keeps inline math away from markdown and leaves prices alone', function (): void {
    expect(render('Energy $E = m_c^2 * a_b$ costs $5 and $10, \\$x\\$ stays.'))
        ->toBe('<p>Energy <span class="math math-inline">E = m_c^2 * a_b</span> costs $5 and $10, $x$ stays.</p>'."\n");
});

it('renders display math blocks', function (string $markdown, string $tex): void {
    expect(render($markdown))->toBe("<div class=\"math math-block\">{$tex}</div>\n");
})->with([
    'multi-line' => ["$$\n\\frac{a}{b} < c\n\\sum_i x_i\n$$", "\\frac{a}{b} &lt; c\n\\sum_i x_i"],
    'one line' => ['$$ x^2 $$', 'x^2'],
    'content on the opening line' => ["$$ a +\nb $$", "a +\nb"],
]);

it('marks mermaid blocks for the preview to draw', function (): void {
    expect(render("```mermaid\nflowchart LR\n  A --> B\n```"))
        ->toContain('<code class="language-mermaid">flowchart LR');
});
