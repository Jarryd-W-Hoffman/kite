let diagramCount = 0;

/**
 * Typeset math and draw diagrams inside rendered markdown. KaTeX and Mermaid are loaded only
 * when a note uses them, so ordinary notes stay light.
 */
export async function enhanceRichBlocks(
    root: HTMLElement,
    dark: boolean,
): Promise<void> {
    await Promise.all([typesetMath(root), drawDiagrams(root, dark)]);
}

async function typesetMath(root: HTMLElement): Promise<void> {
    const elements = [
        ...root.querySelectorAll<HTMLElement>('.math:not([data-typeset])'),
    ];

    if (elements.length === 0) {
        return;
    }

    const [{ default: katex }] = await Promise.all([
        import('katex'),
        import('katex/dist/katex.min.css'),
    ]);

    for (const element of elements) {
        const tex = element.textContent ?? '';

        element.dataset.typeset = 'true';
        element.dataset.tex = tex;
        katex.render(tex, element, {
            displayMode: element.classList.contains('math-block'),
            throwOnError: false,
            output: 'htmlAndMathml',
        });
    }
}

async function drawDiagrams(root: HTMLElement, dark: boolean): Promise<void> {
    const theme = dark ? 'dark' : 'neutral';
    // New diagram blocks, plus diagrams drawn for the other theme.
    const blocks = [
        ...root.querySelectorAll<HTMLElement>('pre > code.language-mermaid'),
        ...root.querySelectorAll<HTMLElement>(
            `figure.mermaid-diagram:not([data-theme="${theme}"])`,
        ),
    ];

    if (blocks.length === 0) {
        return;
    }

    const { default: mermaid } = await import('mermaid');

    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        theme,
        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    });

    for (const block of blocks) {
        const target =
            block.tagName === 'FIGURE' ? block : (block.parentElement ?? block);
        const source =
            block.tagName === 'FIGURE'
                ? (block.dataset.source ?? '')
                : (block.textContent ?? '');
        const figure = document.createElement('figure');

        figure.className = 'mermaid-diagram';
        figure.dataset.test = 'mermaid-diagram';
        figure.dataset.source = source;
        figure.dataset.theme = theme;

        try {
            const { svg } = await mermaid.render(
                `kite-diagram-${++diagramCount}`,
                source,
            );

            figure.innerHTML = svg;
        } catch (error) {
            figure.classList.add('is-invalid');
            figure.textContent = `Diagram error: ${error instanceof Error ? error.message.split('\n')[0] : 'could not draw this diagram'}`;
        }

        target.replaceWith(figure);
    }
}
