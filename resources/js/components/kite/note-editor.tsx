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
    bracketMatching,
    indentOnInput,
    syntaxHighlighting,
} from '@codemirror/language';
import {
    highlightSelectionMatches,
    search,
    searchKeymap,
} from '@codemirror/search';
import { Annotation, Compartment, EditorState } from '@codemirror/state';
import type { Extension } from '@codemirror/state';
import {
    EditorView,
    drawSelection,
    dropCursor,
    highlightActiveLine,
    keymap,
    placeholder,
    rectangularSelection,
} from '@codemirror/view';
import { usePage } from '@inertiajs/react';
import { useEffect, useImperativeHandle, useRef } from 'react';
import type { Ref } from 'react';
import { LinkPeek } from '@/components/kite/link-peek';
import { captureAnchor, findAnchor } from '@/lib/comment-anchors';
import type { AnchorRange, CommentAnchor } from '@/lib/comment-anchors';
import {
    commentAt,
    commentField,
    commentTheme,
    setCommentRanges,
    selectionButtons,
    setCommentStyle,
} from '@/lib/comment-highlights';
import type { SelectionAction } from '@/lib/comment-highlights';
import type { CommentRanges } from '@/lib/comment-highlights';
import {
    frontmatter,
    kiteTheme,
    markdownHighlighting,
    wikiLinks,
    wrapSelections,
} from '@/lib/editor-extensions';
import { livePreview, livePreviewTheme } from '@/lib/live-preview';
import { markdownBlocks, markdownBlocksTheme } from '@/lib/markdown-blocks';
import { findNote } from '@/lib/note-actions';
import { matchNotes, noteName } from '@/lib/note-tree';
import {
    BASE_COMMANDS,
    slashCompletions,
    slashIcon,
} from '@/lib/slash-commands';
import type { SlashCommand } from '@/lib/slash-commands';
import { typewriterScrolling } from '@/lib/typewriter';
import { cn, editorFontClass, editorWidthClass } from '@/lib/utils';
import type { TreeNote, VaultSummary } from '@/types';

export type EditorSelectionText = { from: number; to: number; text: string };

export type NoteEditorHandle = {
    jumpToLine: (line: number) => void;
    /** The main selection, or null when nothing is selected. */
    selection: () => EditorSelectionText | null;
    /**
     * Swap a passage for other text. When the text has moved since it was read, its first
     * occurrence is replaced instead; returns false when it can't be found.
     */
    replacePassage: (passage: EditorSelectionText, insert: string) => boolean;
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
    /** With a vault, holding Ctrl or ⌘ over a link previews the note it goes to. */
    vault?: VaultSummary;
    className?: string;
    testId?: string;
    /** Comments to highlight. Passing onAddComment also offers a Comment button on selections. */
    comments?: EditorComment[];
    activeCommentId?: string | null;
    onCommentRanges?: (ranges: CommentRanges) => void;
    onSelectComment?: (id: string) => void;
    onAddComment?: (anchor: CommentAnchor) => void;
    /** Passing this offers an Extract button on selections, to move them into a new note. */
    onExtract?: (selection: EditorSelectionText) => void;
    /** Keep the cursor's line in the middle of the editor as you write. */
    typewriter?: boolean;
    /** Called with the cursor's 1-based line whenever it moves to another line. */
    onCursorLine?: (line: number) => void;
    /** Commands offered after typing `/`; the built-in blocks when not given. */
    slashCommands?: SlashCommand[];
};

const NO_COMMENTS: EditorComment[] = [];

function selectionOf(view: EditorView): EditorSelectionText | null {
    const { from, to, empty } = view.state.selection.main;

    return empty ? null : { from, to, text: view.state.sliceDoc(from, to) };
}

/** Live preview can be switched on and off without rebuilding the editor. */
const livePreviewMode = new Compartment();

/** Typewriter scrolling, on in focus mode. */
const typewriterMode = new Compartment();

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

export function NoteEditor({
    ref,
    value,
    onChange,
    onSave,
    onUpload,
    onFollowLink,
    notes,
    vault,
    className,
    testId = 'note-editor',
    comments = NO_COMMENTS,
    activeCommentId = null,
    onCommentRanges,
    onSelectComment,
    onAddComment,
    onExtract,
    typewriter = false,
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
        onExtract,
        onCursorLine,
        slashCommands,
    });
    const initialValue = useRef(value);
    const initialTestId = useRef(testId);
    const commentable = useRef(onAddComment !== undefined);
    const extractable = useRef(onExtract !== undefined);
    const initialLivePreview = useRef(editor.live_preview);
    const initialTypewriter = useRef(typewriter);

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
            onExtract,
            onCursorLine,
            slashCommands,
        };
    });

    useImperativeHandle(ref, () => ({
        selection: () =>
            viewRef.current ? selectionOf(viewRef.current) : null,
        replacePassage: (passage, insert) => {
            const view = viewRef.current;

            if (!view) {
                return false;
            }

            const doc = view.state.doc.toString();
            const from =
                doc.slice(passage.from, passage.to) === passage.text
                    ? passage.from
                    : doc.indexOf(passage.text);

            if (from === -1) {
                return false;
            }

            view.dispatch({
                changes: { from, to: from + passage.text.length, insert },
                selection: { anchor: from + insert.length },
                scrollIntoView: true,
            });

            return true;
        },
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

        const selectionActions = [
            commentable.current && {
                label: 'Comment',
                testId: 'add-comment',
                run: addComment,
            },
            extractable.current && {
                label: 'Extract to note',
                testId: 'extract-selection',
                run: (view: EditorView) =>
                    callbacks.current.onExtract?.(selectionOf(view)!),
            },
        ].filter((action): action is SelectionAction => !!action);

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
            typewriterMode.of(
                initialTypewriter.current ? typewriterScrolling : [],
            ),
            commentField,
            commentTheme,
            ...(selectionActions.length > 0
                ? [selectionButtons(selectionActions)]
                : []),
            ...(commentable.current
                ? [keymap.of([{ key: 'Mod-Alt-m', run: addComment }])]
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
        const view = viewRef.current;

        view?.dispatch({
            effects: [
                typewriterMode.reconfigure(
                    typewriter ? typewriterScrolling : [],
                ),
                ...(typewriter
                    ? [
                          EditorView.scrollIntoView(
                              view.state.selection.main.head,
                              { y: 'center' },
                          ),
                      ]
                    : []),
            ],
        });
    }, [typewriter]);

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
        >
            {vault && (
                <LinkPeek
                    vault={vault}
                    containerRef={containerRef}
                    requireModifier
                    resolve={(element) => {
                        const link =
                            element.closest<HTMLElement>('.cm-wikilink');
                        const note = link?.dataset.wikiTarget
                            ? findNote(notes, link.dataset.wikiTarget)
                            : undefined;

                        return note
                            ? {
                                  noteId: note.id,
                                  section: link?.dataset.wikiHeading ?? '',
                              }
                            : null;
                    }}
                />
            )}
        </div>
    );
}

const editorMaxWidth: Record<keyof typeof editorWidthClass, string> = {
    narrow: '42rem',
    normal: '48rem',
    wide: '64rem',
    full: 'none',
};
