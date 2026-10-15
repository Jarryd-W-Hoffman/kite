import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Columns2,
    Eye,
    FileCode,
    FileDown,
    FilePlus2,
    Focus,
    Globe,
    History,
    KeyRound,
    LoaderCircle,
    LockKeyhole,
    LockKeyholeOpen,
    Merge,
    MessageSquare,
    MoreHorizontal,
    PanelRight,
    PencilLine,
    Star,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { CommandPalette } from '@/components/kite/command-palette';
import { CommentRail } from '@/components/kite/comment-rail';
import { DeleteNoteDialog } from '@/components/kite/delete-note-dialog';
import { EmptyState } from '@/components/kite/empty-state';
import { countWords, FocusBar } from '@/components/kite/focus-bar';
import { Kbd } from '@/components/kite/kbd';
import {
    LockedNoteScreen,
    ChangePassphraseDialog,
    RecoveryKeyDialog,
    SetPassphraseDialog,
    UnlockDialog,
    useVaultUnlocked,
} from '@/components/kite/lock-dialogs';
import { NoteEditor } from '@/components/kite/note-editor';
import type {
    EditorSelectionText,
    NoteEditorHandle,
} from '@/components/kite/note-editor';
import { NotePanel } from '@/components/kite/note-panel';
import { NotePreview, scrollToHeading } from '@/components/kite/note-preview';
import type { Properties } from '@/components/kite/properties-editor';
import {
    ExtractNoteDialog,
    MergeNoteDialog,
} from '@/components/kite/refactor-dialogs';
import { SplitPane } from '@/components/kite/split-pane';
import { TabBar } from '@/components/kite/tab-bar';
import { VersionHistoryDialog } from '@/components/kite/version-history-dialog';
import {
    WorkspaceSidebar,
    createNote,
    openDailyNote,
} from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppCommands } from '@/hooks/use-app-commands';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useNoteComments } from '@/hooks/use-note-comments';
import { useNoteDocument } from '@/hooks/use-note-document';
import { useNoteLinkModifiers } from '@/hooks/use-note-link-modifiers';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { requestJson } from '@/lib/kite-http';
import { followWikiLink, uploadAttachments } from '@/lib/note-actions';
import { formatShortcut } from '@/lib/shortcuts';
import { BASE_COMMANDS } from '@/lib/slash-commands';
import type { SlashCommand } from '@/lib/slash-commands';
import {
    booleanCodec,
    idCodec,
    oneOfCodec,
    readStored,
    useStoredState,
    writeStored,
} from '@/lib/storage';
import { cn, editorWidthClass } from '@/lib/utils';
import { isUnlocked, lockVault, onBeforeVaultLock } from '@/lib/vault-keys';
import { show as noteExport } from '@/routes/note-exports';
import { update as updateLock } from '@/routes/note-lock';
import { update as updatePath } from '@/routes/note-path';
import { update as updateProperties } from '@/routes/note-properties';
import { store as publishNote } from '@/routes/note-publications';
import { update as updateStar } from '@/routes/note-star';
import { store as restoreVersion } from '@/routes/note-version-restores';
import { show as showTemplate } from '@/routes/templates';
import type { NoteDetail, OutlineItem, WorkspaceProps } from '@/types';

const VIEW_MODES = ['edit', 'split', 'preview'] as const;

type ViewMode = (typeof VIEW_MODES)[number];

const viewModeCodec = oneOfCodec(VIEW_MODES);
const panelCodec = oneOfCodec(['open', 'closed'] as const);

const splitKey = (vaultId: number) => `kite:split:${vaultId}`;

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

const GETTING_STARTED: { shortcut: string; text: ReactNode }[] = [
    { shortcut: 'Mod+N', text: 'Create a note' },
    { shortcut: 'Mod+K', text: 'Search notes, or create one by name' },
    { shortcut: 'Mod+D', text: "Open today's daily note" },
    { shortcut: 'Mod+G', text: 'See how your notes connect' },
    {
        shortcut: '[[',
        text: 'Link to another note; missing notes are created on click',
    },
    { shortcut: '#', text: 'Tag a note, e.g. #project/kite' },
];

function EmptyWorkspace({ vault, indexing, watching }: WorkspaceProps) {
    useVaultSync(vault, () => router.reload(), watching);

    return (
        <main className="flex flex-1 flex-col">
            <div className="kite-drag h-12 shrink-0 border-b" />
            <div className="flex flex-1 flex-col items-center justify-center gap-8 overflow-y-auto">
                {indexing ? (
                    <EmptyState
                        icon={LoaderCircle}
                        iconClassName="animate-spin"
                        title={`Indexing ${vault.name}…`}
                        description="Your notes will appear as soon as they are read."
                    />
                ) : (
                    <>
                        <EmptyState
                            icon={FilePlus2}
                            title={`${vault.name} is empty`}
                            description={
                                <>
                                    Create your first note, or add .md files to{' '}
                                    <code className="font-mono break-all">
                                        {vault.path}
                                    </code>
                                    .
                                </>
                            }
                        >
                            <Button onClick={() => createNote(vault, 'Home')}>
                                <FilePlus2 />
                                Create a note
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => openDailyNote(vault)}
                            >
                                <CalendarDays />
                                Today&apos;s note
                            </Button>
                        </EmptyState>
                        <section
                            className="w-full max-w-md rounded-xl border bg-card p-4 shadow-kite-sm"
                            data-test="getting-started"
                        >
                            <h2 className="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Getting started
                            </h2>
                            <ul className="space-y-2 text-sm">
                                {GETTING_STARTED.map((tip) => (
                                    <li
                                        key={tip.shortcut}
                                        className="flex items-center gap-3"
                                    >
                                        <Kbd
                                            shortcut={tip.shortcut}
                                            className="w-14 shrink-0 text-center"
                                        />
                                        <span>{tip.text}</span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </>
                )}
            </div>
        </main>
    );
}

/**
 * A locked note while its vault is locked: its name, and the passphrase field to open it.
 */
function LockedWorkspace({
    vault,
    note,
}: WorkspaceProps & { note: NoteDetail }) {
    return (
        <main className="flex min-w-0 flex-1 flex-col">
            <TabBar vault={vault} note={{ id: note.id, title: note.title }} />
            <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-3 text-sm">
                <LockKeyhole className="size-3.5 text-muted-foreground" />
                <span className="truncate font-medium">{note.title}</span>
            </header>
            <LockedNoteScreen
                vault={vault}
                title={note.title}
                onUnlocked={() => router.reload()}
            />
        </main>
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
    const locked = note.locked === true;
    const vaultUnlocked = useVaultUnlocked(vault.id);
    const [lockStep, setLockStep] = useState<
        'passphrase' | 'unlock' | 'change-passphrase' | 'recovery-key' | null
    >(null);
    const [extracting, setExtracting] = useState<EditorSelectionText | null>(
        null,
    );
    const [splitNoteId, setSplitNoteId] = useState<number | null>(() =>
        readStored(splitKey(vault.id), idCodec, null),
    );
    const editorRef = useRef<NoteEditorHandle>(null);
    const { contents, setContents, meta, saveState, save, isDirty, adopt } =
        useNoteDocument(vault, note, {
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
    const tooNarrowToSplit = useMediaQuery('(max-width: 1099px)');
    // Focus mode shows one pane: the editor, or the preview when that's what was showing.
    const shownMode =
        (tooNarrowToSplit || focus) && viewMode === 'split' ? 'edit' : viewMode;
    const [starred, setStarred] = useState(note.starred === true);

    // Save a locked note before its vault locks, then close it.
    useEffect(
        () =>
            onBeforeVaultLock((vaultId) =>
                locked && vaultId === vault.id ? save() : undefined,
            ),
        [locked, vault.id, save],
    );

    useEffect(() => {
        if (locked && !vaultUnlocked) {
            router.reload();
        }
    }, [locked, vaultUnlocked]);

    const lockUrl = updateLock({ vault: vault.id, note: note.id }).url;
    const lockNow = () =>
        void save().then(() =>
            router.put(lockUrl, {}, { preserveScroll: true }),
        );
    const lockNote = () => {
        if (!passphraseSet) {
            setLockStep('passphrase');
        } else if (!isUnlocked(vault.id)) {
            setLockStep('unlock');
        } else {
            lockNow();
        }
    };
    const removeLock = () =>
        void save().then(() =>
            router.delete(lockUrl, { preserveScroll: true }),
        );

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

    const toggleStar = () => {
        const next = !starred;

        setStarred(next);
        router.put(
            updateStar({ vault: vault.id, note: note.id }).url,
            { starred: next },
            {
                only: ['favourites'],
                preserveScroll: true,
                preserveState: true,
                onError: () => setStarred(!next),
            },
        );
    };
    const [properties, setProperties] = useState<Properties>(meta.frontmatter);
    const [savedFrontmatter, setSavedFrontmatter] = useState(meta.frontmatter);

    // A save (or a change on disk) brings the parsed frontmatter back from the server.
    if (meta.frontmatter !== savedFrontmatter) {
        setSavedFrontmatter(meta.frontmatter);
        setProperties(meta.frontmatter);
    }

    const latestContents = useRef(contents);
    const propertyQueue = useRef<Promise<void>>(Promise.resolve());

    useEffect(() => {
        latestContents.current = contents;
    }, [contents]);

    // Changes run one at a time on the newest text, so quick edits never undo each other.
    const changeProperties = (next: Properties) => {
        setProperties(next);
        propertyQueue.current = propertyQueue.current.then(async () => {
            try {
                const response = await requestJson<{
                    contents: string;
                    properties: Properties;
                }>(updateProperties({ vault: vault.id, note: note.id }), {
                    contents: latestContents.current,
                    properties: next,
                });

                latestContents.current = response.contents;
                setContents(response.contents);
                setProperties(response.properties);
            } catch {
                toast.error('Could not update the properties.');
            }
        });
    };
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

    const openSplit = useCallback(
        (id: number | null) => {
            setSplitNoteId(id);
            writeStored(splitKey(vault.id), idCodec, id);
        },
        [vault.id],
    );

    const closeSplit = useCallback(() => openSplit(null), [openSplit]);

    useNoteLinkModifiers(vault.id, openSplit);

    useEffect(() => {
        const onOpenSplit = (event: Event) => {
            openSplit((event as CustomEvent<number>).detail);
        };

        window.addEventListener('kite:open-split', onOpenSplit);

        return () => window.removeEventListener('kite:open-split', onOpenSplit);
    }, [openSplit]);

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

    useEffect(() => {
        const slug = window.location.hash.slice(1);
        const lineLink = /^L(\d+)$/.exec(slug);

        if (lineLink) {
            editorRef.current?.jumpToLine(Number(lineLink[1]));

            return;
        }

        const line = note.outline.find(
            (item) => item.slug === decodeURIComponent(slug),
        )?.line;

        if (slug !== '' && !scrollToHeading(slug) && line !== undefined) {
            editorRef.current?.jumpToLine(line);
        }
    }, [note.outline]);

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
        publish: locked
            ? undefined
            : () =>
                  void save().then(() =>
                      router.post(
                          publishNote({ vault: vault.id, note: note.id }).url,
                          {},
                          { preserveScroll: true },
                      ),
                  ),
    });

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

    const slashCommands = useMemo<SlashCommand[]>(
        () => [
            ...BASE_COMMANDS,
            ...templates.map((template): SlashCommand => ({
                id: `template-${template.id}`,
                label: template.name,
                section: 'Templates',
                description: 'Insert this template',
                keywords: 'template',
                icon: '⧉',
                insert: async () => {
                    const { contents } = await requestJson<{
                        contents: string;
                    }>(
                        showTemplate(
                            { vault: vault.id, path: template.path },
                            {
                                query: {
                                    title: meta.title,
                                    folder: note.path.includes('/')
                                        ? note.path.slice(
                                              0,
                                              note.path.lastIndexOf('/'),
                                          )
                                        : '',
                                },
                            },
                        ),
                    );

                    return { text: contents, block: true };
                },
            })),
        ],
        [templates, vault.id, meta.title, note.path],
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
                <Drawer overlay={narrow} onClose={() => toggleComments(false)}>
                    <CommentRail
                        notes={noteComments}
                        error={commentsError}
                        onSelect={revealComment}
                        onClose={() => toggleComments(false)}
                    />
                </Drawer>
            )}

            {panelOpen && !(narrow && commentsOpen) && (
                <Drawer overlay={narrow} onClose={onTogglePanel}>
                    <aside className="h-full w-72 shrink-0 overflow-y-auto border-l border-border/70 bg-sidebar/40">
                        <NotePanel
                            vault={vault}
                            note={meta}
                            backlinks={backlinks}
                            outgoing={outgoing}
                            mentions={mentions}
                            onSelectHeading={selectHeading}
                            cursorLine={
                                shownMode === 'preview' ? null : cursorLine
                            }
                            properties={properties}
                            onPropertiesChange={changeProperties}
                        />
                    </aside>
                </Drawer>
            )}
        </>
    );

    return (
        <>
            <main
                className="relative flex min-w-0 flex-1 flex-col"
                data-focus={focus || undefined}
            >
                {!focus && (
                    <TabBar
                        vault={vault}
                        note={{ id: note.id, title: meta.title }}
                    />
                )}
                <header
                    className={cn(
                        'kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-3',
                        focus &&
                            'border-transparent opacity-0 transition-opacity duration-300 focus-within:opacity-100 hover:opacity-100',
                    )}
                >
                    {renaming ? (
                        <input
                            autoFocus
                            defaultValue={note.path.replace(/\.md$/i, '')}
                            aria-label="Note path"
                            onBlur={(event) => submitRename(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    submitRename(event.currentTarget.value);
                                } else if (event.key === 'Escape') {
                                    setRenaming(false);
                                }
                            }}
                            className="kite-no-drag min-w-0 flex-1 rounded-md border bg-background px-2 py-1 font-mono text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => setRenaming(true)}
                            title="Rename or move"
                            className="kite-no-drag flex min-w-0 items-center gap-1 rounded-md px-2 py-1 text-[13.5px] transition-colors hover:bg-accent"
                        >
                            <span className="truncate text-muted-foreground">
                                {note.path.includes('/')
                                    ? `${note.path.slice(0, note.path.lastIndexOf('/'))} / `
                                    : ''}
                            </span>
                            <span className="truncate font-medium">
                                {note.path
                                    .split('/')
                                    .pop()
                                    ?.replace(/\.md$/i, '')}
                            </span>
                        </button>
                    )}

                    {locked && (
                        <span
                            className="flex shrink-0 items-center gap-1 rounded-md bg-kite-accent-soft px-1.5 py-0.5 text-[11px] font-medium text-primary"
                            title="Encrypted on disk with the vault’s passphrase"
                            data-test="locked-badge"
                        >
                            <LockKeyhole className="size-3" />
                            Locked
                        </span>
                    )}

                    <span
                        className={cn(
                            'flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground/80',
                            saveState === 'error' && 'text-destructive',
                        )}
                        aria-live="polite"
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'size-1.5 rounded-full bg-emerald-500/70 transition-colors',
                                saveState === 'unsaved' && 'bg-amber-500/80',
                                saveState === 'saving' &&
                                    'animate-pulse bg-primary/70',
                                saveState === 'error' && 'bg-destructive',
                            )}
                        />
                        {
                            {
                                saved: 'Saved',
                                unsaved: 'Edited',
                                saving: 'Saving…',
                                error: 'Not saved',
                            }[saveState]
                        }
                    </span>

                    <div className="kite-no-drag ml-auto flex items-center gap-1">
                        {meta.daily_date && (
                            <div className="mr-1 flex items-center">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label="Previous day"
                                    data-test="previous-day"
                                    onClick={() => shiftDay(-1)}
                                >
                                    <ChevronLeft />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label="Next day"
                                    data-test="next-day"
                                    onClick={() => shiftDay(1)}
                                >
                                    <ChevronRight />
                                </Button>
                            </div>
                        )}
                        <ToggleGroup
                            type="single"
                            size="sm"
                            value={viewMode}
                            onValueChange={changeViewMode}
                            aria-label="View mode"
                            className="mr-1 rounded-lg bg-muted p-0.5 [&_[data-slot=toggle-group-item]]:h-7 [&_[data-slot=toggle-group-item]]:rounded-md [&_[data-slot=toggle-group-item]]:px-2 [&_[data-slot=toggle-group-item]]:text-muted-foreground [&_[data-slot=toggle-group-item][data-state=on]]:bg-background [&_[data-slot=toggle-group-item][data-state=on]]:text-foreground [&_[data-slot=toggle-group-item][data-state=on]]:shadow-kite-sm"
                        >
                            <ToggleGroupItem value="edit" aria-label="Edit">
                                <PencilLine />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="split" aria-label="Split">
                                <Columns2 />
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="preview"
                                aria-label="Preview"
                            >
                                <Eye />
                            </ToggleGroupItem>
                        </ToggleGroup>

                        <Button
                            variant="ghost"
                            size="icon"
                            className={cn(
                                'size-8',
                                starred &&
                                    'text-amber-500 hover:text-amber-500',
                            )}
                            aria-label={
                                starred
                                    ? 'Remove from favourites'
                                    : 'Add to favourites'
                            }
                            aria-pressed={starred}
                            data-test="star-note"
                            onClick={toggleStar}
                        >
                            <Star className={cn(starred && 'fill-current')} />
                        </Button>

                        {!locked && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-1 px-2"
                                aria-label="Toggle comments"
                                aria-pressed={commentsOpen}
                                data-test="toggle-comments"
                                onClick={() => toggleComments(!commentsOpen)}
                            >
                                <MessageSquare />
                                {openComments > 0 && (
                                    <span className="text-xs tabular-nums">
                                        {openComments}
                                    </span>
                                )}
                            </Button>
                        )}

                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            aria-label="Toggle details panel"
                            aria-pressed={panelOpen}
                            onClick={onTogglePanel}
                        >
                            <PanelRight />
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                    aria-label="Note actions"
                                    data-test="note-actions"
                                >
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onSelect={() => setRenaming(true)}
                                >
                                    <PencilLine />
                                    Rename or move
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={onToggleFocus}
                                    data-test="enter-focus"
                                >
                                    <Focus />
                                    Focus mode
                                    <DropdownMenuShortcut>
                                        {formatShortcut(
                                            'Mod+Shift+F',
                                            kite.platform,
                                        )}
                                    </DropdownMenuShortcut>
                                </DropdownMenuItem>
                                {!locked && (
                                    <DropdownMenuItem
                                        onSelect={() => setMerging(true)}
                                        data-test="merge-note"
                                    >
                                        <Merge />
                                        Merge into…
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuItem
                                    onSelect={() => setHistoryOpen(true)}
                                    data-test="open-history"
                                >
                                    <History />
                                    Version history
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                {locked ? (
                                    <>
                                        <DropdownMenuItem
                                            onSelect={removeLock}
                                            data-test="remove-lock"
                                        >
                                            <LockKeyholeOpen />
                                            Remove lock
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                void lockVault(vault.id)
                                            }
                                            data-test="lock-vault"
                                        >
                                            <LockKeyhole />
                                            Lock vault now
                                        </DropdownMenuItem>
                                    </>
                                ) : (
                                    <DropdownMenuItem
                                        onSelect={lockNote}
                                        data-test="lock-note"
                                    >
                                        <LockKeyhole />
                                        Lock note…
                                    </DropdownMenuItem>
                                )}
                                {passphraseSet && (
                                    <DropdownMenuSub>
                                        <DropdownMenuSubTrigger data-test="vault-passphrase-menu">
                                            <KeyRound />
                                            Vault passphrase
                                        </DropdownMenuSubTrigger>
                                        <DropdownMenuSubContent>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setLockStep(
                                                        'change-passphrase',
                                                    )
                                                }
                                                data-test="change-passphrase"
                                            >
                                                Change passphrase…
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    setLockStep('recovery-key')
                                                }
                                                data-test="new-recovery-key"
                                            >
                                                {recoveryKeySet
                                                    ? 'New recovery key…'
                                                    : 'Create recovery key…'}
                                            </DropdownMenuItem>
                                        </DropdownMenuSubContent>
                                    </DropdownMenuSub>
                                )}
                                {!locked && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                void exportNote('html')
                                            }
                                            data-test="export-html"
                                        >
                                            <FileCode />
                                            Export as HTML
                                        </DropdownMenuItem>
                                        {kite.native && (
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    void exportNote('pdf')
                                                }
                                                data-test="export-pdf"
                                            >
                                                <FileDown />
                                                Export as PDF
                                            </DropdownMenuItem>
                                        )}
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                void save().then(() =>
                                                    router.post(
                                                        publishNote({
                                                            vault: vault.id,
                                                            note: note.id,
                                                        }).url,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    ),
                                                )
                                            }
                                            data-test="publish-note"
                                        >
                                            <Globe />
                                            Publish as a web page
                                        </DropdownMenuItem>
                                    </>
                                )}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setDeleting(true)}
                                    data-test="delete-note"
                                >
                                    <Trash2 />
                                    Delete note
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                <div className="relative flex min-h-0 flex-1">
                    {narrow && !focus && sidePanels}
                    {shownMode !== 'preview' && (
                        <NoteEditor
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

            <SetPassphraseDialog
                vault={vault}
                open={lockStep === 'passphrase'}
                onOpenChange={(open) => !open && setLockStep(null)}
                onDone={lockNow}
            />

            <UnlockDialog
                vault={vault}
                open={lockStep === 'unlock'}
                onOpenChange={(open) => !open && setLockStep(null)}
                onUnlocked={lockNow}
            />

            <ChangePassphraseDialog
                vault={vault}
                open={lockStep === 'change-passphrase'}
                onOpenChange={(open) => !open && setLockStep(null)}
            />

            <RecoveryKeyDialog
                vault={vault}
                open={lockStep === 'recovery-key'}
                onOpenChange={(open) => !open && setLockStep(null)}
                replacing={recoveryKeySet}
                onCreated={() => router.reload({ only: ['recovery_key_set'] })}
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

/**
 * A side panel: in line beside the note, or floating over it (with a click-away backdrop) when
 * the window is too narrow for both.
 */
function Drawer({
    overlay,
    onClose,
    children,
}: {
    overlay: boolean;
    onClose: () => void;
    children: ReactNode;
}) {
    if (!overlay) {
        return children;
    }

    return (
        <>
            <div
                className="absolute inset-0 z-30 bg-black/10 dark:bg-black/30"
                onClick={onClose}
                aria-hidden
            />
            <div
                className="absolute inset-y-0 right-0 z-40 flex bg-background shadow-kite-lg"
                data-test="side-drawer"
            >
                {children}
            </div>
        </>
    );
}
