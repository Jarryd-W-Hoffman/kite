import { Link, router } from '@inertiajs/react';
import {
    ChevronRight,
    FilePlus,
    FileText,
    LockKeyhole,
    Folder,
    FolderOpen,
    FolderPlus,
    LayoutTemplate,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-react';
import { createContext, use, useMemo, useRef, useState } from 'react';
import type { DragEvent, KeyboardEvent } from 'react';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { groupColourVariable } from '@/lib/colour-groups';
import type { ColourGroup } from '@/lib/colour-groups';
import { buildTree, noteFolder, noteName } from '@/lib/note-tree';
import type { TreeFolder } from '@/lib/note-tree';
import { readStored, stringListCodec, writeStored } from '@/lib/storage';
import { cn } from '@/lib/utils';
import { update as updateFolder } from '@/routes/folders';
import { update as updatePath } from '@/routes/note-path';
import { show } from '@/routes/notes';
import type { TreeNote, VaultSummary } from '@/types';

type FolderActions = {
    onNewNote: (folder: string) => void;
    onNewFromTemplate: (folder: string) => void;
    onNewFolder: (parent: string) => void;
    onRenameFolder: (folder: string) => void;
    onDeleteFolder: (folder: string) => void;
};

/** What is being dragged around the tree: a note, or a whole folder. */
type Dragged =
    | { kind: 'note'; note: TreeNote }
    | { kind: 'folder'; path: string };

type Props = FolderActions & {
    vault: VaultSummary;
    notes: TreeNote[];
    folders: string[];
    /** Colour groups by id; notes in a group get a coloured dot. Empty to hide the dots. */
    groups: ColourGroup[];
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
    groups: Map<number, ColourGroup>;
    collapsed: Set<string>;
    onToggle: (path: string) => void;
    actions: FolderActions;
    /** Folder a dragged note would be dropped into: '' for the vault root, null when none. */
    dropTarget: string | null;
    dropHandlers: (folder: string) => DropHandlers;
    onDragStart: (event: DragEvent, item: Dragged) => void;
    onDragEnd: () => void;
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

const DRAG_TYPE = 'application/x-kite-tree-item';

function parentOf(item: Dragged): string {
    return noteFolder(item.kind === 'note' ? item.note.path : item.path);
}

/** A folder can't go into itself or one of its own subfolders, and nothing drops where it already is. */
function canDrop(item: Dragged, folder: string): boolean {
    if (parentOf(item) === folder) {
        return false;
    }

    return (
        item.kind === 'note' ||
        (folder !== item.path && !folder.startsWith(`${item.path}/`))
    );
}

function storageKey(vault: VaultSummary): string {
    return `kite:collapsed:${vault.id}`;
}

export function FileTree({
    vault,
    notes,
    folders,
    groups,
    activeNoteId,
    onNewNote,
    onNewFromTemplate,
    onNewFolder,
    onRenameFolder,
    onDeleteFolder,
}: Props) {
    const tree = useMemo(() => buildTree(notes, folders), [notes, folders]);
    const [collapsed, setCollapsed] = useState<Set<string>>(
        () => new Set(readStored(storageKey(vault), stringListCodec, [])),
    );
    const [dropTarget, setDropTarget] = useState<string | null>(null);
    const dragged = useRef<Dragged | null>(null);
    const expandTimer = useRef<{ folder: string; id: number } | null>(null);

    const setFolderCollapsed = (path: string, isCollapsed?: boolean) => {
        setCollapsed((current) => {
            const next = new Set(current);

            if (isCollapsed ?? !next.has(path)) {
                next.add(path);
            } else {
                next.delete(path);
            }

            writeStored(storageKey(vault), stringListCodec, [...next]);

            return next;
        });
    };

    const cancelExpand = () => {
        if (expandTimer.current) {
            window.clearTimeout(expandTimer.current.id);
            expandTimer.current = null;
        }
    };

    const move = (item: Dragged, folder: string) => {
        const path = item.kind === 'note' ? item.note.path : item.path;
        const name = path.split('/').pop() ?? path;
        const destination = folder === '' ? name : `${folder}/${name}`;
        const options = {
            preserveScroll: true,
            preserveState: true,
            onError: (errors: Record<string, string>) =>
                toast.error(
                    errors.path ??
                        errors.to ??
                        `Could not move the ${item.kind}.`,
                ),
        };

        if (item.kind === 'note') {
            router.put(
                updatePath({ vault: vault.id, note: item.note.id }).url,
                { path: destination },
                options,
            );
        } else {
            router.put(
                updateFolder(vault.id).url,
                { path, to: destination },
                options,
            );
        }
    };

    // Every folder row and folder body is a drop zone. Handlers stop propagation so the innermost
    // zone wins; drags that aren't tree items (files, editor text) are left alone.
    const dropHandlers = (folder: string): DropHandlers => ({
        onDragOver: (event) => {
            const item = dragged.current;

            if (!item) {
                return;
            }

            event.stopPropagation();

            if (!canDrop(item, folder)) {
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
            const item = dragged.current;

            if (!item) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            cancelExpand();
            setDropTarget(null);
            dragged.current = null;

            if (canDrop(item, folder)) {
                move(item, folder);
            }
        },
    });

    // Opening a folder's menu action expands it, so whatever gets created there is visible.
    const actions: FolderActions = {
        onNewNote: (folder) => {
            setFolderCollapsed(folder, false);
            onNewNote(folder);
        },
        onNewFromTemplate: (folder) => {
            setFolderCollapsed(folder, false);
            onNewFromTemplate(folder);
        },
        onNewFolder: (parent) => {
            setFolderCollapsed(parent, false);
            onNewFolder(parent);
        },
        onRenameFolder,
        onDeleteFolder,
    };

    if (notes.length === 0 && folders.length === 0) {
        return (
            <div
                className="space-y-2 px-3 py-2 text-sm text-muted-foreground"
                data-test="tree-empty"
            >
                <p>No notes yet.</p>
                <button
                    type="button"
                    onClick={() => onNewNote('')}
                    className="inline-flex items-center gap-1.5 rounded-md text-sidebar-foreground underline-offset-4 hover:underline"
                >
                    <FilePlus className="size-3.5" />
                    Create your first note
                </button>
            </div>
        );
    }

    const context: TreeContextValue = {
        vault,
        activeNoteId,
        groups: new Map(groups.map((group) => [group.id, group])),
        collapsed,
        onToggle: setFolderCollapsed,
        actions,
        dropTarget,
        dropHandlers,
        onDragStart: (event, item) => {
            dragged.current = item;

            if (item.kind === 'note') {
                // Moving within the tree is a move; dropping into the editor copies in a wiki link.
                event.dataTransfer.effectAllowed = 'copyMove';
                event.dataTransfer.setData(DRAG_TYPE, String(item.note.id));
                event.dataTransfer.setData(
                    'text/plain',
                    `[[${noteName(item.note.path)}]]`,
                );
            } else {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData(DRAG_TYPE, item.path);
            }
        },
        onDragEnd: () => {
            dragged.current = null;
            cancelExpand();
            setDropTarget(null);
        },
    };

    /**
     * Arrow keys move through the tree as in a file manager: up and down between rows, right to
     * open a folder (or step into it), left to close one (or step out to its folder).
     */
    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        const items = [
            ...event.currentTarget.querySelectorAll<HTMLElement>(
                '[data-tree-item]',
            ),
        ];
        const index = items.indexOf(document.activeElement as HTMLElement);

        if (index === -1) {
            return;
        }

        const current = items[index];
        const folder = current.dataset.folder;
        const focus = (item: HTMLElement | undefined) => {
            event.preventDefault();
            item?.focus();
        };

        switch (event.key) {
            case 'ArrowDown':
                focus(items[index + 1]);
                break;
            case 'ArrowUp':
                focus(items[index - 1]);
                break;
            case 'Home':
                focus(items[0]);
                break;
            case 'End':
                focus(items.at(-1));
                break;
            case 'ArrowRight':
                if (folder !== undefined && collapsed.has(folder)) {
                    event.preventDefault();
                    setFolderCollapsed(folder, false);
                } else if (folder !== undefined) {
                    const child = items[index + 1];

                    focus(child?.dataset.parent === folder ? child : undefined);
                }
                break;
            case 'ArrowLeft':
                if (folder !== undefined && !collapsed.has(folder)) {
                    event.preventDefault();
                    setFolderCollapsed(folder, true);
                } else {
                    focus(
                        items.find(
                            (item) =>
                                item.dataset.folder === current.dataset.parent,
                        ),
                    );
                }
                break;
        }
    };

    return (
        <TreeContext value={context}>
            <nav
                aria-label="Notes"
                onKeyDown={onKeyDown}
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
        <ul>
            {folder.folders.map((child) => (
                <FolderItem
                    key={child.path}
                    folder={child}
                    depth={depth}
                    indent={indent}
                />
            ))}
            {folder.folders.length === 0 && folder.notes.length === 0 && (
                <li
                    className="py-1 pr-2 text-xs text-muted-foreground italic"
                    style={{ paddingLeft: `${depth * INDENT + 8 + 18}px` }}
                    data-test="tree-empty-folder"
                >
                    Empty
                </li>
            )}
            {folder.notes.map((note) => (
                <li key={note.id}>
                    <Link
                        href={show({ vault: tree.vault.id, note: note.id })}
                        preserveScroll
                        draggable
                        onDragStart={(event: DragEvent) =>
                            tree.onDragStart(event, { kind: 'note', note })
                        }
                        onDragEnd={tree.onDragEnd}
                        aria-current={
                            note.id === tree.activeNoteId ? 'page' : undefined
                        }
                        data-tree-item
                        data-parent={folder.path}
                        data-test="tree-note"
                        data-path={note.path}
                        className={cn(
                            'group/note flex items-center gap-2 rounded-md py-[5px] pr-2 text-[13.5px] text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            note.id === tree.activeNoteId &&
                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                        )}
                        style={indent}
                        title={note.title}
                    >
                        <FileText
                            className={cn(
                                'size-[15px] shrink-0 text-muted-foreground/70',
                                note.id === tree.activeNoteId && 'text-primary',
                            )}
                        />
                        <span className="truncate">{noteName(note.path)}</span>
                        {note.locked && (
                            <LockKeyhole
                                aria-label="Locked"
                                className="size-3 shrink-0 text-muted-foreground/70"
                                data-test="tree-lock"
                            />
                        )}
                        <GroupDot group={tree.groups.get(note.group ?? -1)} />
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
                    draggable
                    onDragStart={(event) =>
                        tree.onDragStart(event, {
                            kind: 'folder',
                            path: folder.path,
                        })
                    }
                    onDragEnd={tree.onDragEnd}
                    onClick={() => tree.onToggle(folder.path)}
                    aria-expanded={!isCollapsed}
                    data-tree-item
                    data-folder={folder.path}
                    data-parent={parentOf({
                        kind: 'folder',
                        path: folder.path,
                    })}
                    className={cn(
                        'flex w-full items-center gap-1.5 rounded-md py-[5px] pr-8 text-left text-[13.5px] text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                        isDropTarget &&
                            'bg-sidebar-accent text-sidebar-accent-foreground ring-1 ring-sidebar-ring',
                    )}
                    style={indent}
                >
                    <ChevronRight
                        className={cn(
                            'size-3.5 shrink-0 text-muted-foreground/70 transition-transform',
                            !isCollapsed && 'rotate-90',
                        )}
                    />
                    {isCollapsed ? (
                        <Folder className="size-[15px] shrink-0 text-muted-foreground/70" />
                    ) : (
                        <FolderOpen className="size-[15px] shrink-0 text-muted-foreground/70" />
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
                            data-test="tree-new-from-template"
                            onSelect={() =>
                                tree.actions.onNewFromTemplate(folder.path)
                            }
                        >
                            <LayoutTemplate />
                            From template…
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
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            data-test="tree-rename-folder"
                            onSelect={() =>
                                tree.actions.onRenameFolder(folder.path)
                            }
                        >
                            <Pencil />
                            Rename…
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            data-test="tree-delete-folder"
                            variant="destructive"
                            onSelect={() =>
                                tree.actions.onDeleteFolder(folder.path)
                            }
                        >
                            <Trash2 />
                            Delete…
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

function GroupDot({ group }: { group: ColourGroup | undefined }) {
    if (!group) {
        return null;
    }

    return (
        <span
            className="ml-auto size-2 shrink-0 rounded-full"
            style={{ background: groupColourVariable(group.colour) }}
            title={group.name}
            data-test="tree-group-dot"
        >
            <span className="sr-only">{group.name}</span>
        </span>
    );
}
