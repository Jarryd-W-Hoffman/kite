import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { CommandPalette } from '@/components/kite/command-palette';
import { CommentRail } from '@/components/kite/comment-rail';
import { ConflictCopies } from '@/components/kite/conflict-copies';
import { DeleteNoteDialog } from '@/components/kite/delete-note-dialog';
import { countWords, FocusBar } from '@/components/kite/focus-bar';
import { NoteLockDialogs } from '@/components/kite/lock-dialogs';
import { NoteActionsMenu } from '@/components/kite/note-actions-menu';
import { NoteEditor } from '@/components/kite/note-editor';
import type {
    EditorSelectionText,
    NoteEditorHandle,
} from '@/components/kite/note-editor';
import { NoteHeader } from '@/components/kite/note-header';
import { NotePanel } from '@/components/kite/note-panel';
import { NotePreview, scrollToHeading } from '@/components/kite/note-preview';
import {
    ExtractNoteDialog,
    MergeNoteDialog,
} from '@/components/kite/refactor-dialogs';
import { ResizeHandle } from '@/components/kite/resize-handle';
import { SaveConflictBanner } from '@/components/kite/save-conflict';
import { SideDrawer } from '@/components/kite/side-drawer';
import { SplitPane } from '@/components/kite/split-pane';
import { TabBar } from '@/components/kite/tab-bar';
import { VersionHistoryDialog } from '@/components/kite/version-history-dialog';
import {
    WorkspaceSidebar,
    openDailyNote,
} from '@/components/kite/workspace-sidebar';
import {
    EmptyWorkspace,
    LockedWorkspace,
} from '@/components/kite/workspace-states';
import { useAppCommands } from '@/hooks/use-app-commands';
import { useClipboard } from '@/hooks/use-clipboard';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useNoteComments } from '@/hooks/use-note-comments';
import { useNoteDocument } from '@/hooks/use-note-document';
import { useNoteFragment } from '@/hooks/use-note-fragment';
import { useNoteLock } from '@/hooks/use-note-lock';
import { useNoteProperties } from '@/hooks/use-note-properties';
import { useNoteSlashCommands } from '@/hooks/use-note-slash-commands';
import { useNoteStar } from '@/hooks/use-note-star';
import { usePanelWidth } from '@/hooks/use-panel-width';
import { useSplitNote } from '@/hooks/use-split-note';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { followWikiLink, uploadAttachments } from '@/lib/note-actions';
import { noteName } from '@/lib/note-tree';
import { DETAILS_WIDTH } from '@/lib/panel-width';
import {
    booleanCodec,
    oneOfCodec,
    readStored,
    useStoredState,
    writeStored,
} from '@/lib/storage';
import { cn, editorWidthClass } from '@/lib/utils';
import { lockVault } from '@/lib/vault-keys';
import { show as noteExport } from '@/routes/note-exports';
import { update as updatePath } from '@/routes/note-path';
import { store as publishNote } from '@/routes/note-publications';
import { store as restoreVersion } from '@/routes/note-version-restores';
import type { NoteDetail, OutlineItem, WorkspaceProps } from '@/types';

const VIEW_MODES = ['edit', 'split', 'preview'] as const;

type ViewMode = (typeof VIEW_MODES)[number];

const viewModeCodec = oneOfCodec(VIEW_MODES);
const panelCodec = oneOfCodec(['open', 'closed'] as const);

export default function NoteShow(props: WorkspaceProps) {
    const { vault, vaults, tree, folders, tags, note, indexing } = props;
    const colourGroups = props.colour_groups;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [panelOpen, setPanelOpen] = useState(
        () => readStored('kite:panel', panelCodec, 'open') === 'open',
    );

    // Focus mode hides everything but the note; it lasts until left, across notes.
    const [focus, setFocus] = useState(false);

    const togglePanel = () => {
        setPanelOpen((open) => {
            writeStored('kite:panel', panelCodec, open ? 'closed' : 'open');

            return !open;
        });
    };

    return (
        <>
            <Head title={note?.title ?? vault.name} />

            {note && (
                <a
                    href="#note"
                    onClick={(event) => {
                        event.preventDefault();
                        document.getElementById('note')?.focus();
                    }}
                    className="sr-only z-50 rounded-md bg-background px-3 py-2 text-sm font-medium shadow-kite-sm focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
                >
                    Skip to note
                </a>
            )}

            <div className="flex h-svh overflow-hidden bg-background">
                {!(focus && note) && (
                    <WorkspaceSidebar
                        vault={vault}
                        vaults={vaults}
                        tree={tree}
                        folders={folders}
                        colourGroups={colourGroups}
                        tags={tags}
                        activeNoteId={note?.id ?? null}
                        indexing={indexing}
                        onSearch={() => setPaletteOpen(true)}
                    />
                )}

                {note?.locked && !note.unlocked ? (
                    <LockedWorkspace {...props} note={note} />
                ) : note ? (
                    <NoteWorkspace
                        key={`${note.id}:${note.unlocked === true}`}
                        {...props}
                        note={note}
                        panelOpen={panelOpen}
                        onTogglePanel={togglePanel}
                        focus={focus}
                        onToggleFocus={() => setFocus((on) => !on)}
                    />
                ) : (
                    <EmptyWorkspace {...props} />
                )}
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
                noteOpen={note !== null}
            />
        </>
    );
}

type NoteWorkspaceProps = WorkspaceProps & {
    note: NoteDetail;
    panelOpen: boolean;
    onTogglePanel: () => void;
    focus: boolean;
    onToggleFocus: () => void;
};

function NoteWorkspace({
    vault,
    watching,
    tree,
    tree_revision: treeRevision,
    note,
    backlinks,
    outgoing,
    mentions,
    comments: initialComments = [],
    comments_error: commentsError,
    templates = [],
    passphrase_set: passphraseSet = false,
    recovery_key_set: recoveryKeySet = false,
    panelOpen,
    onTogglePanel,
    focus,
    onToggleFocus,
}: NoteWorkspaceProps) {
    const { kite } = usePage().props;
    const { editor } = kite;
    // Live preview makes the editor read like the note, so it's the default view.
    const [viewMode, setViewMode] = useState<ViewMode>(() =>
        readStored('kite:view-mode', viewModeCodec, 'edit'),
    );
    const [renaming, setRenaming] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [merging, setMerging] = useState(false);
    const [extracting, setExtracting] = useState<EditorSelectionText | null>(
        null,
    );
    const editorRef = useRef<NoteEditorHandle>(null);
    const [, copy] = useClipboard();
    const {
        contents,
        setContents,
        meta,
        saveState,
        save,
        isDirty,
        adopt,
        savedText,
        saveText,
        conflict,
        keepMine,
        keepMerged,
        takeDisk,
    } = useNoteDocument(vault, note, {
        autosaveMs: editor.autosave_ms,
        // The tree only comes back when the save changed it (a title, tag or property).
        onSaved: (saved) =>
            router.reload({
                only:
                    saved.tree_revision === treeRevision
                        ? ['outgoing']
                        : ['tree', 'tags', 'tree_revision', 'outgoing'],
            }),
    });

    const [commentsOpen, setCommentsOpen] = useStoredState(
        'kite:comments-open',
        booleanCodec,
        false,
    );
    const pendingReveal = useRef<string | null>(null);
    const [cursorLine, setCursorLine] = useState<number | null>(null);
    // Side panels float over the note in narrow windows, and split view needs room for two panes.
    const narrow = useMediaQuery('(max-width: 1279px)');
    const detailsWidth = usePanelWidth('kite:panel-width', DETAILS_WIDTH);
    const tooNarrowToSplit = useMediaQuery('(max-width: 1099px)');
    // Focus mode shows one pane: the editor, or the preview when that's what was showing.
    const shownMode =
        (tooNarrowToSplit || focus) && viewMode === 'split' ? 'edit' : viewMode;
    const { starred, toggleStar } = useNoteStar(
        vault,
        note.id,
        note.starred === true,
    );
    const {
        locked,
        step: lockStep,
        setStep: setLockStep,
        lockNow,
        lockNote,
        removeLock,
    } = useNoteLock(vault, note, save, passphraseSet);

    useEffect(() => {
        if (!focus) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            // Let the editor (closing a menu) and dialogs have Escape first.
            if (
                event.key === 'Escape' &&
                !event.defaultPrevented &&
                document.querySelector('[role=dialog]') === null
            ) {
                onToggleFocus();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [focus, onToggleFocus]);

    const { properties, changeProperties } = useNoteProperties(
        vault,
        note.id,
        meta.frontmatter,
        contents,
        setContents,
    );
    const noteComments = useNoteComments({
        vault,
        noteId: note.id,
        initial: initialComments,
        contents,
        editing: shownMode !== 'preview',
    });
    const openComments = noteComments.comments.filter(
        (comment) => !comment.resolved,
    ).length;

    const toggleComments = (open: boolean) => setCommentsOpen(open);

    const selectComment = (id: string) => {
        noteComments.setActiveId(id);
        toggleComments(true);
    };

    const revealComment = (id: string) => {
        noteComments.setActiveId(id);

        if (shownMode === 'preview') {
            // The editor holds the positions; open it beside the preview, then reveal.
            pendingReveal.current = id;
            changeViewMode('split');
        } else {
            editorRef.current?.revealComment(id);
        }
    };

    useEffect(() => {
        if (pendingReveal.current !== null && noteComments.ranges) {
            editorRef.current?.revealComment(pendingReveal.current);
            pendingReveal.current = null;
        }
    }, [noteComments.ranges]);

    const { splitNoteId, closeSplit } = useSplitNote(vault.id);

    useVaultSync(
        vault,
        () => {
            router.reload({
                only: isDirty()
                    ? [
                          'tree',
                          'folders',
                          'colour_groups',
                          'tags',
                          'backlinks',
                          'outgoing',
                      ]
                    : undefined,
            });
        },
        watching,
    );

    useNoteFragment(editorRef, note.outline);

    const selectHeading = (item: OutlineItem) => {
        scrollToHeading(item.slug);
        editorRef.current?.jumpToLine(item.line);
        window.history.replaceState(window.history.state, '', `#${item.slug}`);
    };

    const changeViewMode = (mode: string) => {
        const parsed = viewModeCodec.parse(mode);

        if (parsed !== undefined) {
            setViewMode(parsed);
            writeStored('kite:view-mode', viewModeCodec, parsed);
        }
    };

    const publish = () =>
        void save().then(() =>
            router.post(
                publishNote({ vault: vault.id, note: note.id }).url,
                {},
                { preserveScroll: true },
            ),
        );

    useAppCommands({
        'toggle-preview': () =>
            setViewMode((mode) => (mode === 'preview' ? 'edit' : 'preview')),
        'toggle-star': () => toggleStar(),
        'toggle-comments': locked
            ? undefined
            : () => toggleComments(!commentsOpen),
        'lock-note': locked ? removeLock : lockNote,
        'lock-vault': () => void lockVault(vault.id),
        'change-passphrase': () =>
            passphraseSet
                ? setLockStep('change-passphrase')
                : toast.info('Lock a note first to choose a vault passphrase.'),
        'recovery-key': () =>
            passphraseSet
                ? setLockStep('recovery-key')
                : toast.info('Lock a note first to choose a vault passphrase.'),
        'toggle-panel': onTogglePanel,
        'version-history': () => setHistoryOpen(true),
        'copy-block-link': () => void copyBlockLink(),
        'extract-selection': () => {
            const selection = editorRef.current?.selection() ?? null;

            if (selection) {
                setExtracting(selection);
            } else {
                toast.info('Select the text to extract first.');
            }
        },
        'merge-note': locked ? undefined : () => setMerging(true),
        'focus-mode': onToggleFocus,
        'export-html': locked ? undefined : () => void exportNote('html'),
        'export-pdf': locked ? undefined : () => void exportNote('pdf'),
        publish: locked ? undefined : publish,
    });

    /** Copy a link to the block the cursor is in, giving it an id first if it has none. */
    const copyBlockLink = async () => {
        const id = editorRef.current?.ensureBlockId() ?? null;

        if (id === null) {
            toast.info('Put the cursor in a paragraph or list item first.');

            return;
        }

        const copied = await copy(`[[${noteName(note.path)}#^${id}]]`);

        if (copied) {
            toast.success('Copied a link to this block.');
        } else {
            toast.error('Could not copy the link.');
        }
    };

    const submitRename = (path: string) => {
        setRenaming(false);

        if (path.trim() === '' || path === note.path) {
            return;
        }

        void save().then(() =>
            router.put(
                updatePath({ vault: vault.id, note: note.id }).url,
                { path },
                {
                    onError: (errors) =>
                        toast.error(
                            errors.path ?? 'Could not rename the note.',
                        ),
                },
            ),
        );
    };

    /** Save first so the export has the latest text, then download it. */
    const exportNote = async (format: 'html' | 'pdf') => {
        await save();

        const link = document.createElement('a');

        link.href = noteExport({
            vault: vault.id,
            note: note.id,
            format,
        }).url;
        link.download = '';
        link.click();
    };

    const slashCommands = useNoteSlashCommands(
        vault,
        templates,
        meta.title,
        note.path,
    );

    const followLink = (target: string) => followWikiLink(vault, tree, target);

    const upload = (files: File[]) => uploadAttachments(vault, files);

    const shiftDay = (days: number) => {
        if (!meta.daily_date) {
            return;
        }

        const [year, month, day] = meta.daily_date.split('-').map(Number);
        const date = new Date(year, month - 1, day + days);
        const pad = (value: number) => String(value).padStart(2, '0');

        openDailyNote(
            vault,
            `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`,
        );
    };

    // Narrow windows show one side panel at a time, floating over the note below its header.
    const sidePanels = (
        <>
            {commentsOpen && !locked && (
                <SideDrawer
                    overlay={narrow}
                    onClose={() => toggleComments(false)}
                >
                    <CommentRail
                        notes={noteComments}
                        error={commentsError}
                        onSelect={revealComment}
                        onClose={() => toggleComments(false)}
                    />
                </SideDrawer>
            )}

            {panelOpen && !(narrow && commentsOpen) && (
                <SideDrawer overlay={narrow} onClose={onTogglePanel}>
                    <aside
                        aria-label="Note details"
                        className="relative h-full shrink-0 border-l border-border/70 bg-sidebar/40"
                        style={{ width: detailsWidth.width }}
                    >
                        <ResizeHandle
                            label="Resize note details"
                            panel={detailsWidth}
                            edge="left"
                        />
                        <div className="h-full overflow-y-auto">
                            <NotePanel
                                vault={vault}
                                note={meta}
                                backlinks={backlinks}
                                outgoing={outgoing}
                                mentions={mentions}
                                onSelectHeading={selectHeading}
                                headingLinks={note.heading_links}
                                cursorLine={
                                    shownMode === 'preview' ? null : cursorLine
                                }
                                properties={properties}
                                onPropertiesChange={changeProperties}
                            />
                        </div>
                    </aside>
                </SideDrawer>
            )}
        </>
    );

    return (
        <>
            <main
                id="note"
                tabIndex={-1}
                aria-labelledby="note-title"
                className="relative flex min-w-0 flex-1 flex-col outline-none"
                data-focus={focus || undefined}
            >
                <h1 id="note-title" className="sr-only">
                    {meta.title}
                </h1>
                {!focus && (
                    <TabBar
                        vault={vault}
                        notes={tree}
                        current={{
                            kind: 'note',
                            id: note.id,
                            title: meta.title,
                        }}
                    />
                )}
                <NoteHeader
                    path={note.path}
                    renaming={renaming}
                    onRenamingChange={setRenaming}
                    onRename={submitRename}
                    locked={locked}
                    saveState={saveState}
                    daily={Boolean(meta.daily_date)}
                    onShiftDay={shiftDay}
                    viewMode={viewMode}
                    onViewModeChange={changeViewMode}
                    starred={starred}
                    onToggleStar={toggleStar}
                    commentsOpen={commentsOpen}
                    openComments={openComments}
                    onToggleComments={() => toggleComments(!commentsOpen)}
                    panelOpen={panelOpen}
                    onTogglePanel={onTogglePanel}
                    focus={focus}
                >
                    <NoteActionsMenu
                        locked={locked}
                        native={kite.native}
                        platform={kite.platform}
                        passphraseSet={passphraseSet}
                        recoveryKeySet={recoveryKeySet}
                        onRename={() => setRenaming(true)}
                        onFocus={onToggleFocus}
                        onMerge={() => setMerging(true)}
                        onHistory={() => setHistoryOpen(true)}
                        onLockNote={lockNote}
                        onRemoveLock={removeLock}
                        onLockVault={() => void lockVault(vault.id)}
                        onChangePassphrase={() =>
                            setLockStep('change-passphrase')
                        }
                        onRecoveryKey={() => setLockStep('recovery-key')}
                        onExport={(format) => void exportNote(format)}
                        onPublish={publish}
                        onDelete={() => setDeleting(true)}
                    />
                </NoteHeader>

                {conflict && (
                    <SaveConflictBanner
                        disk={conflict.contents}
                        mine={contents}
                        base={savedText}
                        onKeepMine={keepMine}
                        onTakeDisk={takeDisk}
                        onMerge={keepMerged}
                    />
                )}

                {!conflict && (
                    <ConflictCopies
                        vault={vault}
                        note={note}
                        mine={contents}
                        onMerged={saveText}
                    />
                )}

                <div className="relative flex min-h-0 flex-1">
                    {narrow && !focus && sidePanels}
                    {shownMode !== 'preview' && (
                        <NoteEditor
                            vault={vault}
                            ref={editorRef}
                            value={contents}
                            onChange={setContents}
                            onSave={() => void save()}
                            onUpload={upload}
                            onFollowLink={followLink}
                            notes={tree}
                            comments={noteComments.editorComments}
                            activeCommentId={noteComments.activeId}
                            onCommentRanges={noteComments.setEditorRanges}
                            onCursorLine={setCursorLine}
                            slashCommands={slashCommands}
                            onSelectComment={selectComment}
                            onAddComment={
                                locked
                                    ? undefined
                                    : (anchor) => {
                                          noteComments.startDraft(anchor);
                                          toggleComments(true);
                                      }
                            }
                            onExtract={setExtracting}
                            typewriter={focus}
                            className={cn(
                                'min-w-0 flex-1',
                                shownMode === 'split' && 'border-r',
                            )}
                        />
                    )}
                    {shownMode !== 'edit' && (
                        <div className="min-w-0 flex-1 overflow-y-auto">
                            <NotePreview
                                vault={vault}
                                html={meta.html}
                                className={cn(
                                    'mx-auto',
                                    editorWidthClass[editor.editor_width],
                                )}
                            />
                        </div>
                    )}
                    {focus && (
                        <FocusBar
                            words={countWords(contents)}
                            goal={
                                typeof properties.word_goal === 'number' &&
                                properties.word_goal > 0
                                    ? properties.word_goal
                                    : null
                            }
                            onGoalChange={(goal) => {
                                const { word_goal: _previous, ...rest } =
                                    properties;

                                changeProperties(
                                    goal === null
                                        ? rest
                                        : { ...properties, word_goal: goal },
                                );
                            }}
                            onExit={onToggleFocus}
                        />
                    )}
                    {!focus &&
                        splitNoteId !== null &&
                        splitNoteId !== note.id && (
                            <SplitPane
                                vault={vault}
                                noteId={splitNoteId}
                                tree={tree}
                                onClose={closeSplit}
                            />
                        )}
                </div>
            </main>

            {!narrow && !focus && sidePanels}

            <VersionHistoryDialog
                vault={vault}
                noteId={note.id}
                current={contents}
                open={historyOpen}
                onOpenChange={setHistoryOpen}
                onRestore={(versionId) =>
                    // Save pending edits first so they are in history too.
                    void save().then(() =>
                        router.post(
                            restoreVersion({
                                vault: vault.id,
                                note: note.id,
                                version: versionId,
                            }).url,
                            {},
                            {
                                onSuccess: (page) => {
                                    const restored = page.props
                                        .note as NoteDetail | null;

                                    if (restored) {
                                        adopt(restored);
                                    }

                                    setHistoryOpen(false);
                                },
                            },
                        ),
                    )
                }
            />

            <DeleteNoteDialog
                vault={vault}
                note={{ id: note.id, title: meta.title }}
                open={deleting}
                onOpenChange={setDeleting}
            />

            <ExtractNoteDialog
                vault={vault}
                note={note}
                text={extracting?.text ?? ''}
                open={extracting !== null}
                onOpenChange={(open) => !open && setExtracting(null)}
                onExtracted={(extracted) => {
                    if (
                        extracting &&
                        !editorRef.current?.replacePassage(
                            extracting,
                            `[[${extracted.link}]]`,
                        )
                    ) {
                        toast.warning(
                            `Created “${extracted.title}”, but the passage had changed, so it wasn't replaced.`,
                        );
                    } else {
                        toast.success(`Extracted to “${extracted.title}”.`, {
                            action: {
                                label: 'Open',
                                onClick: () => router.visit(extracted.url),
                            },
                        });
                    }

                    setExtracting(null);
                }}
            />

            <NoteLockDialogs
                vault={vault}
                step={lockStep}
                onClose={() => setLockStep(null)}
                onReadyToLock={lockNow}
                recoveryKeySet={recoveryKeySet}
            />

            <MergeNoteDialog
                vault={vault}
                note={{ id: note.id, title: meta.title }}
                notes={tree}
                open={merging}
                onOpenChange={setMerging}
                beforeMerge={save}
            />
        </>
    );
}
