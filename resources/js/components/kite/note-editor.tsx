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
import {
    Annotation,
    Compartment,
    EditorSelection,
    EditorState,
} from '@codemirror/state';
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
import { livePreview, livePreviewTheme } from '@/lib/live-preview';
import { markdownBlocks, markdownBlocksTheme } from '@/lib/markdown-blocks';
import { matchNotes, noteName } from '@/lib/note-tree';
import {
    BASE_COMMANDS,
    slashCompletions,
    slashIcon,
} from '@/lib/slash-commands';
import type { SlashCommand } from '@/lib/slash-commands';
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
    /** Commands offered after typing `/`; the built-in blocks when not given. */
    slashCommands?: SlashCommand[];
};

const NO_COMMENTS: EditorComment[] = [];

/** Live preview can be switched on and off without rebuilding the editor. */
const livePreviewMode = new Compartment();

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

const kiteTheme = EditorView.theme({
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
    slashCommands,
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
        slashCommands,
    });
    const initialValue = useRef(value);
    const initialTestId = useRef(testId);
    const commentable = useRef(onAddComment !== undefined);
    const initialLivePreview = useRef(editor.live_preview);

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
            slashCommands,
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
                override: [
                    linkCompletions,
                    slashCompletions(
                        () => callbacks.current.slashCommands ?? BASE_COMMANDS,
                    ),
                ],
                icons: false,
                activateOnTyping: true,
                addToOptions: [slashIcon],
            }),
            placeholder('Start writing… Type / for blocks, [[ to link notes.'),
            kiteTheme,
            markdownBlocks,
            markdownBlocksTheme,
            livePreviewTheme,
            livePreviewMode.of(initialLivePreview.current ? livePreview : []),
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
            effects: livePreviewMode.reconfigure(
                editor.live_preview ? livePreview : [],
            ),
        });
    }, [editor.live_preview]);

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
