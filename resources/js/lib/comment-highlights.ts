import { StateEffect, StateField } from '@codemirror/state';
import type { EditorState, Extension } from '@codemirror/state';
import { Decoration, EditorView, showTooltip } from '@codemirror/view';
import type { DecorationSet, Tooltip } from '@codemirror/view';
import type { AnchorRange } from '@/lib/comment-anchors';

export type CommentRanges = ReadonlyMap<number, AnchorRange>;

type CommentState = {
    ranges: CommentRanges;
    resolved: ReadonlySet<number>;
    active: number | null;
};

/** Replace the tracked comment positions, e.g. after re-finding them in new text. */
export const setCommentRanges = StateEffect.define<CommentRanges>();

/** Which comments are resolved (not highlighted) and which one is selected. */
export const setCommentStyle = StateEffect.define<{
    resolved: ReadonlySet<number>;
    active: number | null;
}>();

/**
 * Comment positions, mapped through every edit so they follow their text. A range whose text is
 * deleted entirely is dropped, which leaves its comment orphaned.
 */
export const commentField = StateField.define<CommentState>({
    create: () => ({ ranges: new Map(), resolved: new Set(), active: null }),
    update(state, transaction) {
        let next = state;

        if (transaction.docChanged && state.ranges.size > 0) {
            const ranges = new Map<number, AnchorRange>();

            for (const [id, range] of state.ranges) {
                const from = transaction.changes.mapPos(range.from, 1);
                const to = transaction.changes.mapPos(range.to, -1);

                if (to > from) {
                    ranges.set(id, { from, to });
                }
            }

            next = { ...next, ranges };
        }

        for (const effect of transaction.effects) {
            if (effect.is(setCommentRanges)) {
                next = { ...next, ranges: effect.value };
            } else if (effect.is(setCommentStyle)) {
                next = { ...next, ...effect.value };
            }
        }

        return next;
    },
    provide: (field) =>
        EditorView.decorations.from(field, (state): DecorationSet => {
            const marks = [];

            for (const [id, range] of state.ranges) {
                if (!state.resolved.has(id)) {
                    marks.push(
                        Decoration.mark({
                            class:
                                id === state.active
                                    ? 'cm-comment cm-comment-active'
                                    : 'cm-comment',
                            attributes: { 'data-comment-id': String(id) },
                        }).range(range.from, range.to),
                    );
                }
            }

            return Decoration.set(marks, true);
        }),
});

/** The comment under a document position, if any (resolved comments are ignored). */
export function commentAt(state: EditorState, position: number): number | null {
    const comments = state.field(commentField);

    for (const [id, range] of comments.ranges) {
        if (
            !comments.resolved.has(id) &&
            position >= range.from &&
            position <= range.to
        ) {
            return id;
        }
    }

    return null;
}

/**
 * A small "Comment" button above a non-empty selection.
 */
export function commentButton(onClick: (view: EditorView) => void): Extension {
    const tooltip = (state: EditorState): Tooltip | null => {
        const range = state.selection.main;

        if (range.empty || state.selection.ranges.length > 1) {
            return null;
        }

        return {
            pos: range.from,
            above: true,
            arrow: false,
            create: (view) => {
                const dom = document.createElement('div');
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'cm-comment-button';
                button.textContent = 'Comment';
                button.dataset.test = 'add-comment';
                // Keep the selection: clicking must not move the cursor first.
                button.addEventListener('mousedown', (event) =>
                    event.preventDefault(),
                );
                button.addEventListener('click', () => onClick(view));
                dom.append(button);

                return { dom };
            },
        };
    };

    return StateField.define<Tooltip | null>({
        create: tooltip,
        update: (value, transaction) =>
            transaction.docChanged || transaction.selection
                ? tooltip(transaction.state)
                : value,
        provide: (field) => showTooltip.from(field),
    });
}

export const commentTheme = EditorView.theme({
    '.cm-comment': {
        backgroundColor:
            'color-mix(in oklch, var(--kite-comment) 22%, transparent)',
        borderBottom:
            '2px solid color-mix(in oklch, var(--kite-comment) 70%, transparent)',
        cursor: 'pointer',
    },
    '.cm-comment-active': {
        backgroundColor:
            'color-mix(in oklch, var(--kite-comment) 45%, transparent)',
    },
    '.cm-tooltip:has(> .cm-comment-button)': {
        border: 'none',
        background: 'transparent',
        boxShadow: 'none',
    },
    '.cm-comment-button': {
        font: 'inherit',
        fontFamily: 'var(--font-sans, system-ui)',
        fontSize: '12px',
        padding: '3px 10px',
        borderRadius: '6px',
        border: '1px solid var(--border)',
        backgroundColor: 'var(--popover)',
        color: 'var(--popover-foreground)',
        boxShadow: '0 4px 12px rgb(0 0 0 / 0.12)',
        cursor: 'pointer',
    },
    '.cm-comment-button:hover': { backgroundColor: 'var(--accent)' },
});
