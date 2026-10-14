import {
    autocompletion,
    closeBrackets,
    closeBracketsKeymap,
    completionKeymap,
} from '@codemirror/autocomplete';
import type { Completion, CompletionContext } from '@codemirror/autocomplete';
import {
    defaultKeymap,
    history,
    historyKeymap,
    indentWithTab,
} from '@codemirror/commands';
import { markdown, markdownLanguage } from '@codemirror/lang-markdown';
import {
    HighlightStyle,
    bracketMatching,
    indentOnInput,
    syntaxHighlighting,
} from '@codemirror/language';
import {
    highlightSelectionMatches,
    search,
    searchKeymap,
} from '@codemirror/search';
import { Annotation, EditorSelection, EditorState } from '@codemirror/state';
import type { Extension } from '@codemirror/state';
import {
    Decoration,
    EditorView,
    MatchDecorator,
    ViewPlugin,
    drawSelection,
    dropCursor,
    highlightActiveLine,
    keymap,
    placeholder,
    rectangularSelection,
} from '@codemirror/view';
import type { DecorationSet, ViewUpdate } from '@codemirror/view';
import { usePage } from '@inertiajs/react';
import { tags } from '@lezer/highlight';
import { useEffect, useImperativeHandle, useRef } from 'react';
import type { Ref } from 'react';
import { matchNotes, noteName } from '@/lib/note-tree';
import { cn, editorFontClass, editorWidthClass } from '@/lib/utils';
import type { TreeNote } from '@/types';

export type NoteEditorHandle = {
    jumpToLine: (line: number) => void;
};

type Props = {
    ref?: Ref<NoteEditorHandle>;
    value: string;
    onChange: (value: string) => void;
    onSave: () => void;
    onUpload?: (files: File[]) => Promise<string[]>;
    onFollowLink?: (target: string) => void;
    notes: TreeNote[];
    className?: string;
    testId?: string;
};

/**
 * Marks document changes that came from outside the editor (e.g. the file changed on disk),
 * so they are not reported back through onChange or added to the undo history.
 */
const External = Annotation.define<boolean>();

const WIKI_LINK = /!?\[\[([^[\]\n|#]*)[^[\]\n]*\]\]/g;

const markdownHighlighting = HighlightStyle.define([
    { tag: tags.heading1, fontSize: '1.6em', fontWeight: '700' },
    { tag: tags.heading2, fontSize: '1.35em', fontWeight: '700' },
    { tag: tags.heading3, fontSize: '1.15em', fontWeight: '600' },
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
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
        fontSize: '0.92em',
    },
    { tag: tags.quote, color: 'var(--muted-foreground)', fontStyle: 'italic' },
    {
        tag: [tags.processingInstruction, tags.meta, tags.contentSeparator],
        color: 'var(--muted-foreground)',
    },
]);

const kiteTheme = EditorView.theme({
    '&': {
        height: '100%',
        fontSize: 'inherit',
        backgroundColor: 'transparent',
    },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': {
        fontFamily: 'inherit',
        lineHeight: '1.9',
        overflow: 'auto',
    },
    '.cm-content': {
        maxWidth: 'var(--kite-editor-width)',
        margin: '0 auto',
        padding: '32px 40px',
        caretColor: 'var(--primary)',
    },
    '.cm-line': { padding: '0' },
    '.cm-cursor': { borderLeftColor: 'var(--primary)' },
    '.cm-activeLine': {
        backgroundColor: 'color-mix(in oklch, var(--muted) 45%, transparent)',
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
        border: '1px solid var(--border)',
        backgroundColor: 'var(--popover)',
        color: 'var(--popover-foreground)',
        borderRadius: '8px',
        boxShadow: '0 8px 24px rgb(0 0 0 / 0.12)',
        overflow: 'hidden',
    },
    '.cm-tooltip-autocomplete > ul > li': { padding: '4px 10px' },
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

const wikiLinks = ViewPlugin.fromClass(
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
function wrapSelections(view: EditorView, marker: string): boolean {
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

export function NoteEditor({
    ref,
    value,
    onChange,
    onSave,
    onUpload,
    onFollowLink,
    notes,
    className,
    testId = 'note-editor',
}: Props) {
    const { editor } = usePage().props.kite;
    const containerRef = useRef<HTMLDivElement>(null);
    const viewRef = useRef<EditorView | null>(null);
    const callbacks = useRef({
        onChange,
        onSave,
        onUpload,
        onFollowLink,
        notes,
    });
    const initialValue = useRef(value);
    const initialTestId = useRef(testId);

    useEffect(() => {
        callbacks.current = { onChange, onSave, onUpload, onFollowLink, notes };
    });

    useImperativeHandle(ref, () => ({
        jumpToLine: (lineNumber: number) => {
            const view = viewRef.current;

            if (!view) {
                return;
            }

            const line = view.state.doc.line(
                Math.min(Math.max(1, lineNumber), view.state.doc.lines),
            );

            view.dispatch({
                selection: { anchor: line.from },
                effects: EditorView.scrollIntoView(line.from, {
                    y: 'start',
                    yMargin: 48,
                }),
            });
            view.focus();
        },
    }));

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const linkCompletions = (context: CompletionContext) => {
            const match = context.matchBefore(/\[\[[^[\]\n|#]*$/);

            if (!match) {
                return null;
            }

            const from = match.from + 2;
            const query = match.text.slice(2);

            return {
                from,
                filter: false,
                options: matchNotes(callbacks.current.notes, query).map(
                    (note): Completion => ({
                        label: noteName(note.path),
                        detail: note.path.includes('/')
                            ? note.path.slice(0, note.path.lastIndexOf('/'))
                            : undefined,
                        apply: (view, completion, applyFrom, to) => {
                            const closing =
                                view.state.sliceDoc(to, to + 2) === ']]'
                                    ? 2
                                    : 0;
                            const insert = `${completion.label}]]`;

                            view.dispatch({
                                changes: {
                                    from: applyFrom,
                                    to: to + closing,
                                    insert,
                                },
                                selection: {
                                    anchor: applyFrom + insert.length,
                                },
                            });
                        },
                    }),
                ),
            };
        };

        const insertUploads = async (
            view: EditorView,
            files: File[],
            position?: number,
        ) => {
            const upload = callbacks.current.onUpload;

            if (!upload || files.length === 0) {
                return;
            }

            const embeds = await upload(files);

            if (embeds.length === 0) {
                return;
            }

            const at = position ?? view.state.selection.main.head;
            const insert = embeds.join('\n');

            view.dispatch({
                changes: { from: at, insert },
                selection: { anchor: at + insert.length },
            });
        };

        const extensions: Extension[] = [
            history(),
            drawSelection(),
            dropCursor(),
            rectangularSelection(),
            highlightActiveLine(),
            highlightSelectionMatches(),
            indentOnInput(),
            bracketMatching(),
            closeBrackets(),
            search({ top: true }),
            EditorView.lineWrapping,
            EditorState.allowMultipleSelections.of(true),
            markdown({ base: markdownLanguage }),
            syntaxHighlighting(markdownHighlighting),
            wikiLinks,
            autocompletion({
                override: [linkCompletions],
                icons: false,
                activateOnTyping: true,
            }),
            placeholder('Start writing… Use [[ to link notes.'),
            kiteTheme,
            keymap.of([
                { key: 'Mod-s', run: () => (callbacks.current.onSave(), true) },
                { key: 'Mod-b', run: (view) => wrapSelections(view, '**') },
                { key: 'Mod-i', run: (view) => wrapSelections(view, '_') },
                ...closeBracketsKeymap,
                ...completionKeymap,
                ...searchKeymap,
                ...historyKeymap,
                indentWithTab,
                // Mod-/ opens the shortcuts sheet instead of toggling an HTML comment.
                ...defaultKeymap.filter((binding) => binding.key !== 'Mod-/'),
            ]),
            EditorView.domEventHandlers({
                paste: (event, view) => {
                    const files = Array.from(event.clipboardData?.files ?? []);

                    if (files.length === 0 || !callbacks.current.onUpload) {
                        return false;
                    }

                    event.preventDefault();
                    void insertUploads(view, files);

                    return true;
                },
                drop: (event, view) => {
                    const files = Array.from(event.dataTransfer?.files ?? []);

                    if (files.length === 0 || !callbacks.current.onUpload) {
                        return false;
                    }

                    event.preventDefault();
                    const position =
                        view.posAtCoords({
                            x: event.clientX,
                            y: event.clientY,
                        }) ?? undefined;
                    void insertUploads(view, files, position);

                    return true;
                },
                mousedown: (event) => {
                    if (!(event.metaKey || event.ctrlKey)) {
                        return false;
                    }

                    const link =
                        event.target instanceof Element
                            ? event.target.closest<HTMLElement>('.cm-wikilink')
                            : null;
                    const target = link?.dataset.wikiTarget;

                    if (!target || !callbacks.current.onFollowLink) {
                        return false;
                    }

                    event.preventDefault();
                    callbacks.current.onFollowLink(target);

                    return true;
                },
            }),
            EditorView.updateListener.of((update) => {
                if (update.selectionSet || update.docChanged) {
                    update.view.dom.dataset.cursor = String(
                        update.state.selection.main.head,
                    );
                }

                const isLocal = update.transactions.some(
                    (transaction) =>
                        transaction.docChanged &&
                        !transaction.annotation(External),
                );

                if (isLocal) {
                    callbacks.current.onChange(update.state.doc.toString());
                }
            }),
            EditorView.contentAttributes.of({
                'aria-label': 'Note contents',
                'data-test': initialTestId.current,
                spellcheck: 'true',
            }),
        ];

        const view = new EditorView({
            parent: container,
            state: EditorState.create({
                doc: initialValue.current,
                extensions,
            }),
        });

        view.dom.dataset.cursor = '0';
        viewRef.current = view;

        return () => {
            view.destroy();
            viewRef.current = null;
        };
    }, []);

    useEffect(() => {
        const view = viewRef.current;

        if (!view || view.state.doc.toString() === value) {
            return;
        }

        const head = Math.min(view.state.selection.main.head, value.length);

        view.dispatch({
            changes: { from: 0, to: view.state.doc.length, insert: value },
            selection: { anchor: head },
            annotations: [External.of(true)],
        });
    }, [value]);

    return (
        <div
            ref={containerRef}
            className={cn(
                'kite-editor relative h-full min-h-0 overflow-hidden text-foreground',
                editorFontClass[editor.editor_font],
                className,
            )}
            style={
                {
                    fontSize: `${editor.editor_font_size}px`,
                    '--kite-editor-width': editorMaxWidth[editor.editor_width],
                } as React.CSSProperties
            }
        />
    );
}

const editorMaxWidth: Record<keyof typeof editorWidthClass, string> = {
    narrow: '42rem',
    normal: '48rem',
    wide: '64rem',
    full: 'none',
};
