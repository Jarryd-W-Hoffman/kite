import { syntaxTree } from '@codemirror/language';
import { StateEffect, StateField } from '@codemirror/state';
import type { EditorState, Extension, Range } from '@codemirror/state';
import {
    Decoration,
    EditorView,
    ViewPlugin,
    WidgetType,
} from '@codemirror/view';
import type { DecorationSet, ViewUpdate } from '@codemirror/view';

/** Marks hidden outright when their line isn't being edited. */
const HIDDEN_MARKS = new Set([
    'EmphasisMark',
    'StrikethroughMark',
    'QuoteMark',
]);

const WIKI_LINK = /!?\[\[([^[\]\n]+?)\]\]/g;

const CALLOUT_MARKER = /^(>\s*)\[![\w-]+\][+-]?\s*/;

class CheckboxWidget extends WidgetType {
    constructor(
        private readonly checked: boolean,
        private readonly position: number,
    ) {
        super();
    }

    eq(other: CheckboxWidget): boolean {
        return (
            other.checked === this.checked && other.position === this.position
        );
    }

    toDOM(view: EditorView): HTMLElement {
        const box = document.createElement('input');

        box.type = 'checkbox';
        box.checked = this.checked;
        box.className = 'cm-kite-checkbox';
        box.setAttribute('aria-label', this.checked ? 'Done' : 'To do');
        box.dataset.test = 'live-checkbox';
        box.addEventListener('mousedown', (event) => {
            event.preventDefault();
            // The marker is "[ ]" or "[x]"; flip the character between the brackets.
            view.dispatch({
                changes: {
                    from: this.position + 1,
                    to: this.position + 2,
                    insert: this.checked ? ' ' : 'x',
                },
            });
        });

        return box;
    }

    ignoreEvent(): boolean {
        return true;
    }
}

class BulletWidget extends WidgetType {
    eq(): boolean {
        return true;
    }

    toDOM(): HTMLElement {
        const bullet = document.createElement('span');

        bullet.className = 'cm-kite-bullet';
        bullet.textContent = '•';

        return bullet;
    }
}

const hide = Decoration.replace({});
const bullet = Decoration.replace({ widget: new BulletWidget() });

/**
 * Lines the cursor or selection touches show their raw markdown, so they can be edited.
 */
function activeLines(state: EditorState): Set<number> {
    const lines = new Set<number>();

    for (const range of state.selection.ranges) {
        const first = state.doc.lineAt(range.from).number;
        const last = state.doc.lineAt(range.to).number;

        for (let number = first; number <= last; number++) {
            lines.add(number);
        }
    }

    return lines;
}

function build(view: EditorView): DecorationSet {
    const { state } = view;
    const active = view.hasFocus ? activeLines(state) : new Set<number>();
    const decorations: Range<Decoration>[] = [];
    const isActive = (position: number) =>
        active.has(state.doc.lineAt(position).number);
    const nextIsSpace = (position: number) =>
        state.sliceDoc(position, position + 1) === ' ';

    for (const { from, to } of view.visibleRanges) {
        syntaxTree(state).iterate({
            from,
            to,
            enter: (node) => {
                // Code and frontmatter are shown as written.
                if (
                    [
                        'FencedCode',
                        'CodeBlock',
                        'Frontmatter',
                        'HTMLBlock',
                    ].includes(node.name)
                ) {
                    return false;
                }

                if (isActive(node.from)) {
                    return;
                }

                const parent = node.node.parent?.name;

                if (node.name === 'Blockquote') {
                    // A callout's [!type] marker reads as its tinted title row instead.
                    const line = state.doc.lineAt(node.from);
                    const marker = CALLOUT_MARKER.exec(line.text);

                    if (marker) {
                        decorations.push(
                            hide.range(
                                line.from + marker[1].length,
                                line.from + marker[0].length,
                            ),
                        );
                    }

                    return;
                }

                if (
                    node.name === 'HeaderMark' &&
                    parent?.startsWith('ATXHeading')
                ) {
                    decorations.push(
                        hide.range(
                            node.from,
                            nextIsSpace(node.to) ? node.to + 1 : node.to,
                        ),
                    );
                } else if (HIDDEN_MARKS.has(node.name)) {
                    const end =
                        node.name === 'QuoteMark' && nextIsSpace(node.to)
                            ? node.to + 1
                            : node.to;
                    decorations.push(hide.range(node.from, end));
                } else if (
                    node.name === 'CodeMark' &&
                    parent === 'InlineCode'
                ) {
                    decorations.push(hide.range(node.from, node.to));
                } else if (node.name === 'Link') {
                    // [text](url): keep the text, drop the brackets and the address.
                    const marks = node.node.getChildren('LinkMark');
                    const url = node.node.getChild('URL');

                    if (marks.length >= 2 && url) {
                        decorations.push(
                            hide.range(marks[0].from, marks[0].to),
                        );
                        decorations.push(hide.range(marks[1].from, node.to));
                    }

                    return false;
                } else if (node.name === 'ListMark' && parent === 'ListItem') {
                    const task = node.node.parent?.getChild('Task');
                    const symbol = state.sliceDoc(node.from, node.to);

                    if (task) {
                        const marker = task.getChild('TaskMarker');

                        if (marker) {
                            const checked = /x/i.test(
                                state.sliceDoc(marker.from, marker.to),
                            );

                            decorations.push(
                                hide.range(node.from, marker.from),
                            );
                            decorations.push(
                                Decoration.replace({
                                    widget: new CheckboxWidget(
                                        checked,
                                        marker.from,
                                    ),
                                }).range(
                                    marker.from,
                                    nextIsSpace(marker.to)
                                        ? marker.to + 1
                                        : marker.to,
                                ),
                            );

                            if (checked) {
                                decorations.push(
                                    Decoration.line({
                                        class: 'cm-kite-done',
                                    }).range(state.doc.lineAt(node.from).from),
                                );
                            }
                        }
                    } else if (['-', '*', '+'].includes(symbol)) {
                        decorations.push(bullet.range(node.from, node.to));
                    }
                }
            },
        });

        // Wiki links aren't in the markdown grammar: show just their text.
        for (let position = from; position <= to;) {
            const line = state.doc.lineAt(position);

            if (!active.has(line.number)) {
                for (const match of line.text.matchAll(WIKI_LINK)) {
                    const start = line.from + match.index;
                    const inner = match[1];
                    const open = match[0].startsWith('!') ? 3 : 2;
                    const alias = inner.indexOf('|');
                    const hiddenEnd =
                        start + open + (alias === -1 ? 0 : alias + 1);

                    decorations.push(hide.range(start, hiddenEnd));
                    decorations.push(
                        hide.range(
                            start + match[0].length - 2,
                            start + match[0].length,
                        ),
                    );
                }
            }

            position = line.to + 1;
        }
    }

    return Decoration.set(decorations, true);
}

/**
 * Obsidian-style live preview: markdown syntax fades away on lines you aren't editing, leaving
 * headings, emphasis, links, bullets and checkboxes as they will read.
 */
const inlinePreview = ViewPlugin.fromClass(
    class {
        decorations: DecorationSet;

        constructor(view: EditorView) {
            this.decorations = build(view);
        }

        update(update: ViewUpdate) {
            if (
                update.docChanged ||
                update.selectionSet ||
                update.viewportChanged ||
                update.focusChanged ||
                syntaxTree(update.startState) !== syntaxTree(update.state)
            ) {
                this.decorations = build(update.view);
            }
        }
    },
    { decorations: (plugin) => plugin.decorations },
);

export const livePreviewTheme = EditorView.theme({
    '.cm-kite-checkbox': {
        appearance: 'none',
        width: '1.05em',
        height: '1.05em',
        margin: '0 0.45em 0 0',
        verticalAlign: '-0.17em',
        border: '1.5px solid color-mix(in oklch, var(--foreground) 35%, transparent)',
        borderRadius: '4px',
        cursor: 'pointer',
        display: 'inline-grid',
        placeContent: 'center',
    },
    '.cm-kite-checkbox:checked': {
        backgroundColor: 'var(--primary)',
        borderColor: 'var(--primary)',
    },
    '.cm-kite-checkbox:checked::after': {
        content: '""',
        width: '0.3em',
        height: '0.55em',
        border: 'solid var(--primary-foreground)',
        borderWidth: '0 2px 2px 0',
        transform: 'translateY(-0.06em) rotate(45deg)',
    },
    '.cm-kite-done': {
        color: 'var(--muted-foreground)',
        textDecoration: 'line-through',
        textDecorationColor:
            'color-mix(in oklch, var(--muted-foreground) 50%, transparent)',
    },
    '.cm-kite-properties': {
        display: 'inline-flex',
        alignItems: 'center',
        gap: '6px',
        margin: '0 0 12px',
        padding: '2px 10px',
        borderRadius: '999px',
        border: '1px solid var(--border)',
        backgroundColor: 'color-mix(in oklch, var(--muted) 60%, transparent)',
        color: 'var(--muted-foreground)',
        fontFamily: 'var(--font-sans)',
        fontSize: '12px',
        cursor: 'pointer',
    },
    '.cm-kite-properties::before': {
        content: '"≡"',
        fontWeight: '700',
    },
    '.cm-kite-properties:hover': {
        color: 'var(--foreground)',
        borderColor: 'color-mix(in oklch, var(--primary) 40%, transparent)',
    },
    '.cm-kite-bullet': {
        color: 'color-mix(in oklch, var(--foreground) 45%, transparent)',
        display: 'inline-block',
        width: '0.6em',
    },
});

const FRONTMATTER = /^---[ \t]*\n([\s\S]*?)\n(?:---|\.\.\.)[ \t]*(?:\n|$)/;

class PropertiesWidget extends WidgetType {
    constructor(private readonly count: number) {
        super();
    }

    eq(other: PropertiesWidget): boolean {
        return other.count === this.count;
    }

    toDOM(view: EditorView): HTMLElement {
        const chip = document.createElement('div');

        chip.className = 'cm-kite-properties';
        chip.dataset.test = 'properties-chip';
        chip.textContent = `${this.count} ${this.count === 1 ? 'property' : 'properties'}`;
        chip.title = 'Show the frontmatter';
        chip.addEventListener('mousedown', (event) => {
            event.preventDefault();
            // Step inside the block to edit it as text.
            view.dispatch({ selection: { anchor: 4 } });
            view.focus();
        });

        return chip;
    }

    ignoreEvent(): boolean {
        return true;
    }
}

function collapsedFrontmatter(
    state: EditorState,
    focused: boolean,
): DecorationSet {
    const match = FRONTMATTER.exec(state.doc.sliceString(0, 20_000));

    if (!match) {
        return Decoration.none;
    }

    const end = match[0].endsWith('\n') ? match[0].length - 1 : match[0].length;
    const editing =
        focused && state.selection.ranges.some((range) => range.from <= end);

    if (editing) {
        return Decoration.none;
    }

    const count = match[1]
        .split('\n')
        .filter((line) => /^[^\s#-][^:]*:/.test(line)).length;

    return Decoration.set(
        Decoration.replace({
            widget: new PropertiesWidget(count),
            block: true,
        }).range(0, end),
    );
}

const setFocused = StateEffect.define<boolean>();

/**
 * Frontmatter folds into a "3 properties" chip (it's edited in the Properties panel), opening
 * back up as text while the cursor is inside it.
 */
const frontmatterChip = StateField.define<{
    focused: boolean;
    decorations: DecorationSet;
}>({
    create: (state) => ({
        focused: false,
        decorations: collapsedFrontmatter(state, false),
    }),
    update: (value, transaction) => {
        let focused = value.focused;

        for (const effect of transaction.effects) {
            if (effect.is(setFocused)) {
                focused = effect.value;
            }
        }

        return focused !== value.focused ||
            transaction.docChanged ||
            transaction.selection
            ? {
                  focused,
                  decorations: collapsedFrontmatter(transaction.state, focused),
              }
            : value;
    },
    provide: (field) => [
        EditorView.decorations.from(field, (value) => value.decorations),
        EditorView.focusChangeEffect.of((_state, focusing) =>
            setFocused.of(focusing),
        ),
    ],
});

/**
 * Obsidian-style live preview: markdown syntax on other lines, and the frontmatter block.
 */
export const livePreview: Extension = [inlinePreview, frontmatterChip];
