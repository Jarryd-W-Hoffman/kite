import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Columns2,
    Eye,
    FilePlus2,
    MoreHorizontal,
    PanelRight,
    PencilLine,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { CommandPalette } from '@/components/kite/command-palette';
import { NoteEditor } from '@/components/kite/note-editor';
import type { NoteEditorHandle } from '@/components/kite/note-editor';
import { NotePanel } from '@/components/kite/note-panel';
import { NotePreview, scrollToHeading } from '@/components/kite/note-preview';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { requestJson, uploadFile } from '@/lib/kite-http';
import { cn } from '@/lib/utils';
import { update as updatePath } from '@/routes/note-path';
import { destroy, update } from '@/routes/notes';
import { store as storeAttachment } from '@/routes/vault-attachments';
import type {
    NoteDetail,
    NoteMeta,
    OutlineItem,
    WorkspaceProps,
} from '@/types';

type ViewMode = 'edit' | 'split' | 'preview';
type SaveState = 'saved' | 'unsaved' | 'saving' | 'error';

const AUTOSAVE_DELAY_MS = 400;

function storedViewMode(): ViewMode {
    const stored = localStorage.getItem('kite:view-mode');

    return stored === 'edit' || stored === 'preview' ? stored : 'split';
}

export default function NoteShow(props: WorkspaceProps) {
    const { vault, vaults, tree, tags, note, indexing } = props;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [panelOpen, setPanelOpen] = useState(
        () => localStorage.getItem('kite:panel') !== 'closed',
    );

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const modifier = event.metaKey || event.ctrlKey;

            if (modifier && event.key === 'k') {
                event.preventDefault();
                setPaletteOpen((open) => !open);
            } else if (modifier && event.key === 'n') {
                event.preventDefault();
                createNote(vault);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [vault]);

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

function EmptyWorkspace({ vault, indexing, watching }: WorkspaceProps) {
    useVaultSync(vault, () => router.reload(), watching);

    return (
        <main className="flex flex-1 flex-col">
            <div className="kite-drag h-12 shrink-0 border-b" />
            <div className="flex flex-1 flex-col items-center justify-center gap-4 text-center">
                <FilePlus2 className="size-10 text-muted-foreground" />
                <div className="space-y-1">
                    <h1 className="text-lg font-semibold">
                        {indexing
                            ? `Indexing ${vault.name}…`
                            : `${vault.name} is empty`}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Create your first note, or add .md files to{' '}
                        <code className="font-mono">{vault.path}</code>.
                    </p>
                </div>
                <Button onClick={() => createNote(vault, 'Home')}>
                    Create a note
                </Button>
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
    panelOpen,
    onTogglePanel,
}: NoteWorkspaceProps) {
    const [contents, setContents] = useState(note.contents);
    const [meta, setMeta] = useState<NoteMeta>(note);
    const [saveState, setSaveState] = useState<SaveState>('saved');
    const [viewMode, setViewMode] = useState<ViewMode>(storedViewMode);
    const [renaming, setRenaming] = useState(false);
    const [savedText, setSavedText] = useState(note.contents);
    const [serverHash, setServerHash] = useState(note.hash);
    const savedContents = useRef(note.contents);
    const editorRef = useRef<NoteEditorHandle>(null);
    const latestContents = useRef(contents);

    if (note.hash !== serverHash) {
        setServerHash(note.hash);

        if (contents === savedText) {
            setContents(note.contents);
            setSavedText(note.contents);
            setMeta(note);
        }
    }

    useEffect(() => {
        latestContents.current = contents;
        savedContents.current = savedText;
    });

    const isDirty = () => latestContents.current !== savedContents.current;

    const save = useCallback(async () => {
        const pending = latestContents.current;

        if (pending === savedContents.current) {
            return;
        }

        setSaveState('saving');

        try {
            const saved = await requestJson<NoteMeta>(
                update({ vault: vault.id, note: note.id }),
                { contents: pending },
            );

            savedContents.current = pending;
            setSavedText(pending);
            setMeta(saved);
            setSaveState(
                latestContents.current === pending ? 'saved' : 'unsaved',
            );
            router.reload({ only: ['tree', 'tags', 'outgoing'] });
        } catch {
            setSaveState('error');
            toast.error('Could not save the note.');
        }
    }, [vault.id, note.id]);

    useEffect(() => {
        if (contents === savedText) {
            return;
        }

        const timeout = window.setTimeout(() => void save(), AUTOSAVE_DELAY_MS);

        return () => window.clearTimeout(timeout);
    }, [contents, savedText, save]);

    useEffect(() => () => void save(), [save]);

    useVaultSync(
        vault,
        () => {
            router.reload({
                only: isDirty()
                    ? ['tree', 'tags', 'backlinks', 'outgoing']
                    : undefined,
            });
        },
        watching,
    );

    useEffect(() => {
        const slug = window.location.hash.slice(1);
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

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'e') {
                event.preventDefault();
                setViewMode((mode) =>
                    mode === 'preview' ? 'edit' : 'preview',
                );
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

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

    const upload = async (files: File[]): Promise<string[]> => {
        const embeds: string[] = [];

        for (const file of files) {
            try {
                const result = await uploadFile<{ embed: string }>(
                    storeAttachment(vault.id),
                    file,
                );

                embeds.push(result.embed);
            } catch {
                toast.error(`Could not attach “${file.name}”.`);
            }
        }

        return embeds;
    };

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

    const deleteNote = () => {
        if (window.confirm(`Delete “${meta.title}”? This removes the file.`)) {
            router.delete(destroy({ vault: vault.id, note: note.id }).url);
        }
    };

    return (
        <>
            <main className="flex min-w-0 flex-1 flex-col">
                <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b px-4">
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
                            className="kite-no-drag flex min-w-0 items-center gap-1 rounded-md px-1.5 py-1 text-sm hover:bg-accent"
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
                            'shrink-0 text-xs text-muted-foreground',
                            saveState === 'error' && 'text-destructive',
                        )}
                        aria-live="polite"
                    >
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
                                    variant="destructive"
                                    onSelect={deleteNote}
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
                            onChange={(value) => {
                                setContents(value);
                                setSaveState('unsaved');
                            }}
                            onSave={() => void save()}
                            onUpload={upload}
                            notes={tree}
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
                                className="mx-auto max-w-3xl"
                            />
                        </div>
                    )}
                </div>
            </main>

            {panelOpen && (
                <aside className="w-72 shrink-0 overflow-y-auto border-l">
                    <NotePanel
                        vault={vault}
                        note={meta}
                        backlinks={backlinks}
                        outgoing={outgoing}
                        mentions={mentions}
                        onSelectHeading={selectHeading}
                    />
                </aside>
            )}
        </>
    );
}
