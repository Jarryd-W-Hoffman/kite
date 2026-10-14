import { syntaxTree } from '@codemirror/language';
import { RangeSetBuilder } from '@codemirror/state';
import { Decoration, EditorView, ViewPlugin } from '@codemirror/view';
import type { DecorationSet, ViewUpdate } from '@codemirror/view';

/** Block-level markdown nodes styled as whole lines, and the class each one gets. */
const BLOCKS: Record<string, string> = {
    FencedCode: 'cm-kite-code',
    CodeBlock: 'cm-kite-code',
    Blockquote: 'cm-kite-quote',
    Frontmatter: 'cm-kite-frontmatter',
};

function build(view: EditorView): DecorationSet {
    const builder = new RangeSetBuilder<Decoration>();
    const { doc } = view.state;
    const styled = new Map<number, string[]>();

    for (const { from, to } of view.visibleRanges) {
        syntaxTree(view.state).iterate({
            from,
            to,
            enter: (node) => {
                const block = BLOCKS[node.name];

                if (!block) {
                    return;
                }

                const first = doc.lineAt(node.from).number;
                const last = doc.lineAt(node.to).number;

                for (let number = first; number <= last; number++) {
                    const classes = [block];

                    if (number === first) {
                        classes.push(`${block}-first`);
                    }

                    if (number === last) {
                        classes.push(`${block}-last`);
                    }

                    styled.set(number, [
                        ...(styled.get(number) ?? []),
                        ...classes,
                    ]);
                }

                // Nested blocks are covered by their parent's lines.
                return false;
            },
        });
    }

    for (const number of [...styled.keys()].sort((a, b) => a - b)) {
        builder.add(
            doc.line(number).from,
            doc.line(number).from,
            Decoration.line({ class: styled.get(number)?.join(' ') }),
        );
    }

    return builder.finish();
}

/**
 * Gives code blocks, quotes and frontmatter a shape in the editor (a tinted block, an accent bar,
 * a quiet panel) while keeping every markdown character visible and editable.
 */
export const markdownBlocks = ViewPlugin.fromClass(
    class {
        decorations: DecorationSet;

        constructor(view: EditorView) {
            this.decorations = build(view);
        }

        update(update: ViewUpdate) {
            if (
                update.docChanged ||
                update.viewportChanged ||
                syntaxTree(update.startState) !== syntaxTree(update.state)
            ) {
                this.decorations = build(update.view);
            }
        }
    },
    { decorations: (plugin) => plugin.decorations },
);

const block = 'color-mix(in oklch, var(--muted) 75%, transparent)';

export const markdownBlocksTheme = EditorView.theme({
    '.cm-kite-code': {
        fontFamily: 'var(--font-mono)',
        fontSize: '0.88em',
        backgroundColor: block,
        paddingInline: '14px !important',
        borderInline: '1px solid var(--border)',
    },
    // Code inside a block is plain text on the block's tint, not inline-code chips.
    '.cm-kite-code span': {
        color: 'var(--foreground)',
        backgroundColor: 'transparent',
        padding: '0',
        fontSize: 'inherit',
    },
    '.cm-kite-code-first': {
        borderTop: '1px solid var(--border)',
        borderTopLeftRadius: '10px',
        borderTopRightRadius: '10px',
        paddingTop: '4px !important',
    },
    '.cm-kite-code-last': {
        borderBottom: '1px solid var(--border)',
        borderBottomLeftRadius: '10px',
        borderBottomRightRadius: '10px',
        paddingBottom: '4px !important',
    },
    '.cm-kite-quote': {
        borderLeft:
            '3px solid color-mix(in oklch, var(--primary) 55%, transparent)',
        paddingLeft: '14px !important',
        color: 'color-mix(in oklch, var(--foreground) 72%, transparent)',
    },
    '.cm-kite-frontmatter': {
        fontFamily: 'var(--font-mono)',
        fontSize: '0.82em',
        color: 'var(--muted-foreground)',
        backgroundColor: 'color-mix(in oklch, var(--muted) 55%, transparent)',
        paddingInline: '12px !important',
    },
    '.cm-kite-frontmatter-first': {
        borderTopLeftRadius: '8px',
        borderTopRightRadius: '8px',
        paddingTop: '6px !important',
    },
    '.cm-kite-frontmatter-last': {
        borderBottomLeftRadius: '8px',
        borderBottomRightRadius: '8px',
        paddingBottom: '6px !important',
        marginBottom: '12px',
    },
});
