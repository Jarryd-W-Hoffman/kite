import { Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    FilePlus,
    FileText,
    Folder,
    FolderOpen,
    FolderPlus,
    MoreHorizontal,
} from 'lucide-react';
import { createContext, use, useMemo, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { buildTree, noteFolder, noteName } from '@/lib/note-tree';
import type { TreeFolder } from '@/lib/note-tree';
import { cn } from '@/lib/utils';
import { update as updatePath } from '@/routes/note-path';
import { show } from '@/routes/notes';
import type { TreeNote, VaultSummary } from '@/types';

type FolderActions = {
    onNewNote: (folder: string) => void;
    onNewFolder: (parent: string) => void;
};

type Props = FolderActions & {
    vault: VaultSummary;
    notes: TreeNote[];
    folders: string[];
    activeNoteId: number | null;
};

type DropHandlers = {
    onDragOver: (event: DragEvent) => void;
    onDragLeave: (event: DragEvent) => void;
    onDrop: (event: DragEvent) => void;
};

type TreeContextValue = {
    vault: VaultSummary;
    activeNoteId: number | null;
    collapsed: Set<string>;
    onToggle: (path: string) => void;
    actions: FolderActions;
    /** Folder a dragged note would be dropped into: '' for the vault root, null when none. */
    dropTarget: string | null;
    dropHandlers: (folder: string) => DropHandlers;
    onNoteDragStart: (event: DragEvent, note: TreeNote) => void;
    onNoteDragEnd: () => void;
};

const TreeContext = createContext<TreeContextValue | null>(null);

function useTree(): TreeContextValue {
    const context = use(TreeContext);

    if (!context) {
        throw new Error('useTree must be used inside a FileTree.');
    }

    return context;
}

/** Horizontal step, in pixels, between one level of the tree and the next. */
const INDENT = 16;

/** How long a dragged note hovers over a collapsed folder before it opens. */
const EXPAND_DELAY_MS = 600;

const NOTE_DRAG_TYPE = 'application/x-kite-note';

function storageKey(vault: VaultSummary): string {
    return `kite:collapsed:${vault.id}`;
}

export function FileTree({
    vault,
    notes,
    folders,
    activeNoteId,
    onNewNote,
    onNewFolder,
}: Props) {
    const tree = useMemo(() => buildTree(notes, folders), [notes, folders]);
    const [collapsed, setCollapsed] = useState<Set<string>>(() => {
        try {
            return new Set(
                JSON.parse(
                    localStorage.getItem(storageKey(vault)) ?? '[]',
                ) as string[],
            );
        } catch {
            return new Set();
        }
    });
    const [dropTarget, setDropTarget] = useState<string | null>(null);
    const dragged = useRef<TreeNote | null>(null);
    const expandTimer = useRef<{ folder: string; id: number } | null>(null);

    const setFolderCollapsed = (path: string, isCollapsed?: boolean) => {
        setCollapsed((current) => {
            const next = new Set(current);

            if (isCollapsed ?? !next.has(path)) {
                next.add(path);
            } else {
                next.delete(path);
            }

            localStorage.setItem(storageKey(vault), JSON.stringify([...next]));

            return next;
        });
    };

    const cancelExpand = () => {
        if (expandTimer.current) {
            window.clearTimeout(expandTimer.current.id);
            expandTimer.current = null;
        }
    };

    const moveNote = (note: TreeNote, folder: string) => {
        const name = note.path.split('/').pop() ?? note.path;

        router.put(
            updatePath({ vault: vault.id, note: note.id }).url,
            { path: folder === '' ? name : `${folder}/${name}` },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    toast.error(errors.path ?? 'Could not move the note.'),
            },
        );
    };

    // Every folder row and folder body is a drop zone. Handlers stop propagation so the innermost
    // zone wins; drags that aren't tree notes (files, editor text) are left alone.
    const dropHandlers = (folder: string): DropHandlers => ({
        onDragOver: (event) => {
            const note = dragged.current;

            if (!note) {
                return;
            }

            event.stopPropagation();

            if (noteFolder(note.path) === folder) {
                event.dataTransfer.dropEffect = 'none';
                setDropTarget(null);

                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            setDropTarget(folder);

            if (
                folder !== '' &&
                collapsed.has(folder) &&
                expandTimer.current?.folder !== folder
            ) {
                cancelExpand();
                expandTimer.current = {
                    folder,
                    id: window.setTimeout(() => {
                        expandTimer.current = null;
                        setFolderCollapsed(folder, false);
                    }, EXPAND_DELAY_MS),
                };
            }
        },
        onDragLeave: (event) => {
            if (
                event.currentTarget.contains(event.relatedTarget as Node | null)
            ) {
                return;
            }

            setDropTarget((current) => (current === folder ? null : current));

            if (expandTimer.current?.folder === folder) {
                cancelExpand();
            }
        },
        onDrop: (event) => {
            const note = dragged.current;

            if (!note) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            cancelExpand();
            setDropTarget(null);
            dragged.current = null;

            if (noteFolder(note.path) !== folder) {
                moveNote(note, folder);
            }
        },
    });

    // Opening a folder's menu action expands it, so whatever gets created there is visible.
    const actions: FolderActions = {
        onNewNote: (folder) => {
            setFolderCollapsed(folder, false);
            onNewNote(folder);
        },
        onNewFolder: (parent) => {
            setFolderCollapsed(parent, false);
            onNewFolder(parent);
        },
    };

    if (notes.length === 0 && folders.length === 0) {
        return (
            <p className="px-3 py-2 text-sm text-muted-foreground">
                No notes yet.
            </p>
        );
    }

    const context: TreeContextValue = {
        vault,
        activeNoteId,
        collapsed,
        onToggle: setFolderCollapsed,
        actions,
        dropTarget,
        dropHandlers,
        onNoteDragStart: (event, note) => {
            dragged.current = note;
            // Moving within the tree is a move; dropping into the editor copies in a wiki link.
            event.dataTransfer.effectAllowed = 'copyMove';
            event.dataTransfer.setData(NOTE_DRAG_TYPE, String(note.id));
            event.dataTransfer.setData(
                'text/plain',
                `[[${noteName(note.path)}]]`,
            );
        },
        onNoteDragEnd: () => {
            dragged.current = null;
            cancelExpand();
            setDropTarget(null);
        },
    };

    return (
        <TreeContext value={context}>
            <nav
                aria-label="Notes"
                data-test="file-tree"
                className={cn(
                    'min-h-16 rounded-md pb-2 text-sm',
                    dropTarget === '' && 'bg-sidebar-accent/60',
                )}
                {...dropHandlers('')}
            >
                <FolderContents folder={tree} depth={0} />
            </nav>
        </TreeContext>
    );
}

function FolderContents({
    folder,
    depth,
}: {
    folder: TreeFolder;
    depth: number;
}) {
    const tree = useTree();

    // Notes line up with their sibling folders' chevrons, so items at the same level share an edge.
    const indent = { paddingLeft: `${depth * INDENT + 8}px` };

    return (
        <ul role="group">
            {folder.folders.map((child) => (
                <FolderItem
                    key={child.path}
                    folder={child}
                    depth={depth}
                    indent={indent}
                />
            ))}
            {folder.notes.map((note) => (
                <li key={note.id}>
                    <Link
                        href={show({ vault: tree.vault.id, note: note.id })}
                        preserveScroll
                        draggable
                        onDragStart={(event: DragEvent) =>
                            tree.onNoteDragStart(event, note)
                        }
                        onDragEnd={tree.onNoteDragEnd}
                        aria-current={
                            note.id === tree.activeNoteId ? 'page' : undefined
                        }
                        data-test="tree-note"
                        data-path={note.path}
                        className={cn(
                            'flex items-center gap-1.5 rounded-md py-1 pr-2 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            note.id === tree.activeNoteId &&
                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                        )}
                        style={indent}
                        title={note.title}
                    >
                        <FileText className="size-4 shrink-0 text-muted-foreground" />
                        <span className="truncate">{noteName(note.path)}</span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function FolderItem({
    folder,
    depth,
    indent,
}: {
    folder: TreeFolder;
    depth: number;
    indent: { paddingLeft: string };
}) {
    const tree = useTree();
    const isCollapsed = tree.collapsed.has(folder.path);
    const isDropTarget = tree.dropTarget === folder.path;

    return (
        <li {...tree.dropHandlers(folder.path)}>
            <div
                className="group relative"
                data-test="tree-folder"
                data-path={folder.path}
            >
                <button
                    type="button"
                    onClick={() => tree.onToggle(folder.path)}
                    aria-expanded={!isCollapsed}
                    className={cn(
                        'flex w-full items-center gap-1.5 rounded-md py-1 pr-8 text-left text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                        isDropTarget &&
                            'bg-sidebar-accent text-sidebar-accent-foreground ring-1 ring-sidebar-ring',
                    )}
                    style={indent}
                >
                    <ChevronRight
                        className={cn(
                            'size-3.5 shrink-0 transition-transform',
                            !isCollapsed && 'rotate-90',
                        )}
                    />
                    {isCollapsed ? (
                        <Folder className="size-4 shrink-0" />
                    ) : (
                        <FolderOpen className="size-4 shrink-0" />
                    )}
                    <span className="truncate">{folder.name}</span>
                </button>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            aria-label={`Actions for ${folder.name}`}
                            data-test="tree-folder-actions"
                            className="absolute top-1/2 right-1 flex size-6 -translate-y-1/2 items-center justify-center rounded text-muted-foreground opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-sidebar-border focus-visible:opacity-100 data-[state=open]:opacity-100"
                        >
                            <MoreHorizontal className="size-4" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-44">
                        <DropdownMenuItem
                            data-test="tree-new-note"
                            onSelect={() => tree.actions.onNewNote(folder.path)}
                        >
                            <FilePlus />
                            New note
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-test="tree-new-folder"
                            onSelect={() =>
                                tree.actions.onNewFolder(folder.path)
                            }
                        >
                            <FolderPlus />
                            New folder
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            {!isCollapsed && (
                <div className="relative">
                    <span
                        aria-hidden
                        className={cn(
                            'pointer-events-none absolute inset-y-0 w-px bg-sidebar-border',
                            isDropTarget && 'bg-sidebar-ring',
                        )}
                        style={{ left: `${depth * INDENT + 8 + 7}px` }}
                    />
                    <FolderContents folder={folder} depth={depth + 1} />
                </div>
            )}
        </li>
    );
}
