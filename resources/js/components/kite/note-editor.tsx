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
import { captureAnchor, findAnchor } from '@/lib/comment-anchors';
import type { AnchorRange, CommentAnchor } from '@/lib/comment-anchors';
import {
    commentAt,
    commentButton,
    commentField,
    commentTheme,
    setCommentRanges,
    setCommentStyle,
} from '@/lib/comment-highlights';
import type { CommentRanges } from '@/lib/comment-highlights';
import { matchNotes, noteName } from '@/lib/note-tree';
import { cn, editorFontClass, editorWidthClass } from '@/lib/utils';
import type { TreeNote } from '@/types';

export type NoteEditorHandle = {
    jumpToLine: (line: number) => void;
    /** Select a comment's passage and scroll it into view. */
    revealComment: (id: string) => void;
};

export type EditorComment = CommentAnchor & { id: string; resolved: boolean };

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
    /** Comments to highlight. Passing onAddComment also offers a Comment button on selections. */
    comments?: EditorComment[];
    activeCommentId?: string | null;
    onCommentRanges?: (ranges: CommentRanges) => void;
    onSelectComment?: (id: string) => void;
    onAddComment?: (anchor: CommentAnchor) => void;
    /** Called with the cursor's 1-based line whenever it moves to another line. */
    onCursorLine?: (line: number) => void;
};

const NO_COMMENTS: EditorComment[] = [];

/**
 * Re-find comments in the document. Comments already tracked keep their mapped position unless
 * `refind` is set (the whole text was replaced, so mapping says nothing about where they went).
 */
function anchorComments(
    view: EditorView,
    comments: EditorComment[],
    refind: boolean,
): void {
    const tracked = view.state.field(commentField).ranges;
    const doc = view.state.doc.toString();
    const ranges = new Map<string, AnchorRange>();

    for (const comment of comments) {
        const range =
            (!refind && tracked.get(comment.id)) || findAnchor(doc, comment);

        if (range) {
            ranges.set(comment.id, range);
        }
    }

    view.dispatch({ effects: setCommentRanges.of(ranges) });
}

/**
 * Marks document changes that came from outside the editor (e.g. the file changed on disk),
 * so they are not reported back through onChange or added to the undo history.
 */
const External = Annotation.define<boolean>();

const WIKI_LINK = /!?\[\[([^[\]\n|#]*)[^[\]\n]*\]\]/g;

const FENCE = /^---\s*$/;

/**
 * Parse a leading `---` block as frontmatter, so its last line and closing fence are not read as
 * a setext heading. Unclosed frontmatter runs to the end, as it does while it is being typed.
 */
const frontmatter: NonNullable<
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
    comments = NO_COMMENTS,
    activeCommentId = null,
    onCommentRanges,
    onSelectComment,
    onAddComment,
    onCursorLine,
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
        comments,
        onCommentRanges,
        onSelectComment,
        onAddComment,
        onCursorLine,
    });
    const initialValue = useRef(value);
    const initialTestId = useRef(testId);
    const commentable = useRef(onAddComment !== undefined);

    useEffect(() => {
        callbacks.current = {
            onChange,
            onSave,
            onUpload,
            onFollowLink,
            notes,
            comments,
            onCommentRanges,
            onSelectComment,
            onAddComment,
            onCursorLine,
        };
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
        revealComment: (id: string) => {
            const view = viewRef.current;
            const range = view?.state.field(commentField).ranges.get(id);

            if (!view || !range) {
                return;
            }

            view.dispatch({
                selection: { anchor: range.from, head: range.to },
                effects: EditorView.scrollIntoView(range.from, {
                    y: 'center',
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

        const addComment = (view: EditorView): boolean => {
            const { from, to, empty } = view.state.selection.main;

            if (empty) {
                return false;
            }

            callbacks.current.onAddComment?.(
                captureAnchor(view.state.doc.toString(), from, to),
            );

            return true;
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
            markdown({ base: markdownLanguage, extensions: [frontmatter] }),
            syntaxHighlighting(markdownHighlighting),
            wikiLinks,
            autocompletion({
                override: [linkCompletions],
                icons: false,
                activateOnTyping: true,
            }),
            placeholder('Start writing… Use [[ to link notes.'),
            kiteTheme,
            commentField,
            commentTheme,
            ...(commentable.current
                ? [
                      commentButton(addComment),
                      keymap.of([{ key: 'Mod-Alt-m', run: addComment }]),
                  ]
                : []),
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
                click: (event, view) => {
                    const position = view.posAtCoords({
                        x: event.clientX,
                        y: event.clientY,
                    });
                    const id =
                        position === null
                            ? null
                            : commentAt(view.state, position);

                    if (id !== null && view.state.selection.main.empty) {
                        callbacks.current.onSelectComment?.(id);
                    }

                    return false;
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
                const ranges = update.state.field(commentField).ranges;

                if (ranges !== update.startState.field(commentField).ranges) {
                    callbacks.current.onCommentRanges?.(ranges);
                }

                if (update.selectionSet || update.docChanged) {
                    const { head } = update.state.selection.main;
                    const line = update.state.doc.lineAt(head).number;

                    update.view.dom.dataset.cursor = String(head);

                    if (
                        line !==
                        update.startState.doc.lineAt(
                            update.startState.selection.main.head,
                        ).number
                    ) {
                        callbacks.current.onCursorLine?.(line);
                    }
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
        anchorComments(view, callbacks.current.comments, true);
    }, [value]);

    useEffect(() => {
        const view = viewRef.current;

        if (view) {
            anchorComments(view, comments, false);
        }
    }, [comments]);

    useEffect(() => {
        viewRef.current?.dispatch({
            effects: setCommentStyle.of({
                resolved: new Set(
                    comments
                        .filter((comment) => comment.resolved)
                        .map((comment) => comment.id),
                ),
                active: activeCommentId,
            }),
        });
    }, [comments, activeCommentId]);

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
