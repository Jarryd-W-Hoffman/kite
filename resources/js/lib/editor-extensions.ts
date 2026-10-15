import { markdown } from '@codemirror/lang-markdown';
import { HighlightStyle } from '@codemirror/language';
import { EditorSelection } from '@codemirror/state';
import {
    Decoration,
    EditorView,
    MatchDecorator,
    ViewPlugin,
} from '@codemirror/view';
import type { DecorationSet, ViewUpdate } from '@codemirror/view';
import { tags } from '@lezer/highlight';

/*
 * The CodeMirror setup behind the note editor that doesn't depend on a particular note: how
 * frontmatter parses, Markdown highlighting, the editor theme and wiki link marks.
 */

export const WIKI_LINK = /!?\[\[([^[\]\n|#]*)[^[\]\n]*\]\]/g;

const FENCE = /^---\s*$/;

/**
 * Parse a leading `---` block as frontmatter, so its last line and closing fence are not read as
 * a setext heading. Unclosed frontmatter runs to the end, as it does while it is being typed.
 */
export const frontmatter: NonNullable<
    NonNullable<Parameters<typeof markdown>[0]>['extensions']
> = {
    defineNodes: [{ name: 'Frontmatter', block: true, style: tags.meta }],
    parseBlock: [
        {
            name: 'Frontmatter',
            before: 'HorizontalRule',
            parse(cx, line) {
                if (cx.lineStart !== 0 || !FENCE.test(line.text)) {
                    return false;
                }

                let end = line.text.length;

                while (cx.nextLine()) {
                    end = cx.lineStart + line.text.length;

                    if (FENCE.test(line.text) || line.text === '...') {
                        cx.nextLine();
                        break;
                    }
                }

                cx.addElement(cx.elt('Frontmatter', 0, end));

                return true;
            },
        },
    ],
};

export const markdownHighlighting = HighlightStyle.define([
    {
        tag: tags.heading1,
        fontSize: '1.85em',
        fontWeight: '700',
        letterSpacing: '-0.02em',
    },
    {
        tag: tags.heading2,
        fontSize: '1.4em',
        fontWeight: '650',
        letterSpacing: '-0.015em',
    },
    { tag: tags.heading3, fontSize: '1.18em', fontWeight: '650' },
    {
        tag: [tags.heading4, tags.heading5, tags.heading6],
        fontWeight: '600',
    },
    { tag: tags.strong, fontWeight: '700' },
    { tag: tags.emphasis, fontStyle: 'italic' },
    { tag: tags.strikethrough, textDecoration: 'line-through' },
    { tag: [tags.link, tags.url], color: 'var(--kite-link)' },
    {
        tag: tags.monospace,
        fontFamily: 'var(--font-mono)',
        fontSize: '0.88em',
        color: 'oklch(0.58 0.15 20)',
        backgroundColor: 'color-mix(in oklch, var(--muted) 85%, transparent)',
        borderRadius: '4px',
        padding: '1px 3px',
    },
    { tag: tags.quote, fontStyle: 'normal' },
    {
        tag: [tags.processingInstruction, tags.meta, tags.contentSeparator],
        color: 'color-mix(in oklch, var(--muted-foreground) 70%, transparent)',
    },
]);

export const kiteTheme = EditorView.theme({
    '&': {
        height: '100%',
        fontSize: 'inherit',
        backgroundColor: 'transparent',
    },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': {
        fontFamily: 'inherit',
        lineHeight: '1.8',
        overflow: 'auto',
    },
    '.cm-content': {
        maxWidth: 'var(--kite-editor-width)',
        margin: '0 auto',
        padding: '56px 48px 40vh',
        caretColor: 'var(--primary)',
    },
    '.cm-line': { padding: '0' },
    '.cm-activeLine': { backgroundColor: 'transparent' },
    '.cm-cursor, .cm-dropCursor': {
        borderLeftWidth: '2px',
        borderLeftColor: 'var(--primary)',
    },
    '&.cm-focused .cm-selectionBackground, .cm-selectionBackground': {
        backgroundColor:
            'color-mix(in oklch, var(--primary) 18%, transparent) !important',
    },
    '.cm-placeholder': { color: 'var(--muted-foreground)' },
    '.cm-wikilink': {
        color: 'var(--kite-link)',
        textDecoration: 'underline',
        textDecorationColor:
            'color-mix(in oklch, var(--kite-link) 35%, transparent)',
        textUnderlineOffset: '3px',
    },
    '.cm-tooltip': {
        fontFamily: 'var(--font-sans)',
        fontSize: '13.5px',
        border: '1px solid var(--border)',
        backgroundColor: 'var(--popover)',
        color: 'var(--popover-foreground)',
        borderRadius: '8px',
        boxShadow: 'var(--kite-shadow-lg)',
        overflow: 'hidden',
    },
    '.cm-tooltip-autocomplete > ul': {
        maxHeight: '22rem !important',
        padding: '4px',
    },
    '.cm-tooltip-autocomplete > ul > li': {
        padding: '4px 10px',
        borderRadius: '6px',
    },
    '.cm-tooltip-autocomplete > ul > completion-section': {
        display: 'block',
        padding: '8px 10px 4px',
        fontSize: '11px',
        fontWeight: '600',
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        color: 'var(--muted-foreground)',
        borderBottom: 'none',
    },
    '.cm-kite-slash-icon': {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '28px',
        height: '28px',
        marginRight: '10px',
        borderRadius: '6px',
        border: '1px solid var(--border)',
        backgroundColor: 'var(--background)',
        fontSize: '12px',
        fontWeight: '600',
        verticalAlign: 'middle',
        color: 'var(--muted-foreground)',
    },
    '.cm-tooltip-autocomplete li:has(.cm-kite-slash-icon)': {
        display: 'flex',
        alignItems: 'center',
        minWidth: '19rem',
        padding: '5px 8px !important',
    },
    '.cm-tooltip-autocomplete li:has(.cm-kite-slash-icon) .cm-completionLabel':
        {
            fontWeight: '500',
        },
    '.cm-tooltip-autocomplete li:has(.cm-kite-slash-icon) .cm-completionDetail':
        {
            marginLeft: 'auto',
            paddingLeft: '16px',
            fontSize: '12px',
        },
    '.cm-tooltip-autocomplete > ul > li[aria-selected]': {
        backgroundColor: 'var(--accent)',
        color: 'var(--accent-foreground)',
    },
    '.cm-completionDetail': {
        marginLeft: '8px',
        color: 'var(--muted-foreground)',
        fontStyle: 'normal',
    },
    '.cm-panels': {
        backgroundColor: 'var(--background)',
        borderColor: 'var(--border)',
    },
});

const wikiLinkDecorator = new MatchDecorator({
    regexp: WIKI_LINK,
    decoration: (match) =>
        Decoration.mark({
            class: 'cm-wikilink',
            attributes: { 'data-wiki-target': match[1].trim() },
        }),
});

export const wikiLinks = ViewPlugin.fromClass(
    class {
        decorations: DecorationSet;

        constructor(view: EditorView) {
            this.decorations = wikiLinkDecorator.createDeco(view);
        }

        update(update: ViewUpdate) {
            this.decorations = wikiLinkDecorator.updateDeco(
                update,
                this.decorations,
            );
        }
    },
    { decorations: (plugin) => plugin.decorations },
);

/**
 * Wrap every selection range in a markdown marker, e.g. ** for bold.
 */
export function wrapSelections(view: EditorView, marker: string): boolean {
    view.dispatch(
        view.state.changeByRange((range) => ({
            changes: [
                { from: range.from, insert: marker },
                { from: range.to, insert: marker },
            ],
            range: EditorSelection.range(
                range.from + marker.length,
                range.to + marker.length,
            ),
        })),
    );

    return true;
}
