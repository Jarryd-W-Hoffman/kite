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
    Globe,
    History,
    LoaderCircle,
    MessageSquare,
    MoreHorizontal,
    PanelRight,
    PencilLine,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { CommandPalette } from '@/components/kite/command-palette';
import { CommentRail } from '@/components/kite/comment-rail';
import { DeleteNoteDialog } from '@/components/kite/delete-note-dialog';
import { EmptyState } from '@/components/kite/empty-state';
import { Kbd } from '@/components/kite/kbd';
import { NoteEditor } from '@/components/kite/note-editor';
import type { NoteEditorHandle } from '@/components/kite/note-editor';
import { NotePanel } from '@/components/kite/note-panel';
import { NotePreview, scrollToHeading } from '@/components/kite/note-preview';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppCommands } from '@/hooks/use-app-commands';
import { useNoteComments } from '@/hooks/use-note-comments';
import { useNoteDocument } from '@/hooks/use-note-document';
import { useNoteLinkModifiers } from '@/hooks/use-note-link-modifiers';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { requestJson } from '@/lib/kite-http';
import { followWikiLink, uploadAttachments } from '@/lib/note-actions';
import { BASE_COMMANDS } from '@/lib/slash-commands';
import type { SlashCommand } from '@/lib/slash-commands';
import { cn, editorWidthClass } from '@/lib/utils';
import { show as noteExport } from '@/routes/note-exports';
import { update as updatePath } from '@/routes/note-path';
import { store as publishNote } from '@/routes/note-publications';
import { store as restoreVersion } from '@/routes/note-version-restores';
import { show as showTemplate } from '@/routes/templates';
import type { NoteDetail, OutlineItem, WorkspaceProps } from '@/types';

type ViewMode = 'edit' | 'split' | 'preview';

function storedSplit(vaultId: number): number | null {
    const stored = Number(localStorage.getItem(`kite:split:${vaultId}`));

    return Number.isInteger(stored) && stored > 0 ? stored : null;
}

function storedViewMode(): ViewMode {
    const stored = localStorage.getItem('kite:view-mode');

    return stored === 'edit' || stored === 'preview' ? stored : 'split';
}

export default function NoteShow(props: WorkspaceProps) {
    const { vault, vaults, tree, folders, tags, note, indexing } = props;
    const colourGroups = props.colour_groups;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [panelOpen, setPanelOpen] = useState(
        () => localStorage.getItem('kite:panel') !== 'closed',
    );

    const togglePanel = () => {
        setPanelOpen((open) => {
            localStorage.setItem('kite:panel', open ? 'closed' : 'open');

            return !open;
        });
    };

    return (
        <>
            <Head title={note?.title ?? vault.name} />

            <div className="flex h-svh overflow-hidden bg-background">
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

                {note ? (
                    <NoteWorkspace
                        key={note.id}
                        {...props}
                        note={note}
                        panelOpen={panelOpen}
                        onTogglePanel={togglePanel}
                    />
                ) : (
                    <EmptyWorkspace {...props} />
                )}
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
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

type NoteWorkspaceProps = WorkspaceProps & {
    note: NoteDetail;
    panelOpen: boolean;
    onTogglePanel: () => void;
};

function NoteWorkspace({
    vault,
    watching,
    tree,
    note,
    backlinks,
    outgoing,
    mentions,
    comments: initialComments = [],
    comments_error: commentsError,
    templates = [],
    panelOpen,
    onTogglePanel,
}: NoteWorkspaceProps) {
    const { kite } = usePage().props;
    const { editor } = kite;
    const [viewMode, setViewMode] = useState<ViewMode>(storedViewMode);
    const [renaming, setRenaming] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [splitNoteId, setSplitNoteId] = useState<number | null>(() =>
        storedSplit(vault.id),
    );
    const editorRef = useRef<NoteEditorHandle>(null);
    const { contents, setContents, meta, saveState, save, isDirty, adopt } =
        useNoteDocument(vault, note, {
            autosaveMs: editor.autosave_ms,
            onSaved: () =>
                router.reload({ only: ['tree', 'tags', 'outgoing'] }),
        });

    const [commentsOpen, setCommentsOpen] = useState(
        () => localStorage.getItem('kite:comments-open') === 'true',
    );
    const pendingReveal = useRef<string | null>(null);
    const [cursorLine, setCursorLine] = useState<number | null>(null);
    const noteComments = useNoteComments({
        vault,
        noteId: note.id,
        initial: initialComments,
        contents,
        editing: viewMode !== 'preview',
    });
    const openComments = noteComments.comments.filter(
        (comment) => !comment.resolved,
    ).length;

    const toggleComments = (open: boolean) => {
        setCommentsOpen(open);
        localStorage.setItem('kite:comments-open', String(open));
    };

    const selectComment = (id: string) => {
        noteComments.setActiveId(id);
        toggleComments(true);
    };

    const revealComment = (id: string) => {
        noteComments.setActiveId(id);

        if (viewMode === 'preview') {
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

            if (id === null) {
                localStorage.removeItem(`kite:split:${vault.id}`);
            } else {
                localStorage.setItem(`kite:split:${vault.id}`, String(id));
            }
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
        if (mode === 'edit' || mode === 'split' || mode === 'preview') {
            setViewMode(mode);
            localStorage.setItem('kite:view-mode', mode);
        }
    };

    useAppCommands({
        'toggle-preview': () =>
            setViewMode((mode) => (mode === 'preview' ? 'edit' : 'preview')),
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

    return (
        <>
            <main className="flex min-w-0 flex-1 flex-col">
                <TabBar
                    vault={vault}
                    note={{ id: note.id, title: meta.title }}
                />
                <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-3">
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
                                    onSelect={() => setHistoryOpen(true)}
                                    data-test="open-history"
                                >
                                    <History />
                                    Version history
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => void exportNote('html')}
                                    data-test="export-html"
                                >
                                    <FileCode />
                                    Export as HTML
                                </DropdownMenuItem>
                                {kite.native && (
                                    <DropdownMenuItem
                                        onSelect={() => void exportNote('pdf')}
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
                                                { preserveScroll: true },
                                            ),
                                        )
                                    }
                                    data-test="publish-note"
                                >
                                    <Globe />
                                    Publish as a web page
                                </DropdownMenuItem>
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

                <div className="flex min-h-0 flex-1">
                    {viewMode !== 'preview' && (
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
                            onAddComment={(anchor) => {
                                noteComments.startDraft(anchor);
                                toggleComments(true);
                            }}
                            className={cn(
                                'min-w-0 flex-1',
                                viewMode === 'split' && 'border-r',
                            )}
                        />
                    )}
                    {viewMode !== 'edit' && (
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
                    {splitNoteId !== null && splitNoteId !== note.id && (
                        <SplitPane
                            vault={vault}
                            noteId={splitNoteId}
                            tree={tree}
                            onClose={closeSplit}
                        />
                    )}
                </div>
            </main>

            {commentsOpen && (
                <CommentRail
                    notes={noteComments}
                    error={commentsError}
                    onSelect={revealComment}
                    onClose={() => toggleComments(false)}
                />
            )}

            {panelOpen && (
                <aside className="w-72 shrink-0 overflow-y-auto border-l border-border/70 bg-sidebar/40">
                    <NotePanel
                        vault={vault}
                        note={meta}
                        backlinks={backlinks}
                        outgoing={outgoing}
                        mentions={mentions}
                        onSelectHeading={selectHeading}
                        cursorLine={viewMode === 'preview' ? null : cursorLine}
                    />
                </aside>
            )}

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
        </>
    );
}
