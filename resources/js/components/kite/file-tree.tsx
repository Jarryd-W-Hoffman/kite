import { Link } from '@inertiajs/react';
import {
    ChevronRight,
    FilePlus,
    FileText,
    Folder,
    FolderOpen,
    FolderPlus,
    MoreHorizontal,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { buildTree, noteName } from '@/lib/note-tree';
import type { TreeFolder } from '@/lib/note-tree';
import { cn } from '@/lib/utils';
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

    return (
        <nav aria-label="Notes" className="text-sm">
            <FolderContents
                folder={tree}
                depth={0}
                vault={vault}
                activeNoteId={activeNoteId}
                collapsed={collapsed}
                onToggle={setFolderCollapsed}
                actions={actions}
            />
        </nav>
    );
}

type FolderContentsProps = {
    folder: TreeFolder;
    depth: number;
    vault: VaultSummary;
    activeNoteId: number | null;
    collapsed: Set<string>;
    onToggle: (path: string) => void;
    actions: FolderActions;
};

function FolderContents({
    folder,
    depth,
    vault,
    activeNoteId,
    collapsed,
    onToggle,
    actions,
}: FolderContentsProps) {
    const indent = { paddingLeft: `${depth * 12 + 8}px` };

    return (
        <ul role="group">
            {folder.folders.map((child) => {
                const isCollapsed = collapsed.has(child.path);

                return (
                    <li key={child.path}>
                        <div className="group relative" data-test="tree-folder">
                            <button
                                type="button"
                                onClick={() => onToggle(child.path)}
                                aria-expanded={!isCollapsed}
                                className="flex w-full items-center gap-1.5 rounded-md py-1 pr-8 text-left text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
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
                                <span className="truncate">{child.name}</span>
                            </button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        type="button"
                                        aria-label={`Actions for ${child.name}`}
                                        data-test="tree-folder-actions"
                                        className="absolute top-1/2 right-1 flex size-6 -translate-y-1/2 items-center justify-center rounded text-muted-foreground opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-sidebar-border focus-visible:opacity-100 data-[state=open]:opacity-100"
                                    >
                                        <MoreHorizontal className="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="w-44"
                                >
                                    <DropdownMenuItem
                                        data-test="tree-new-note"
                                        onSelect={() =>
                                            actions.onNewNote(child.path)
                                        }
                                    >
                                        <FilePlus />
                                        New note
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        data-test="tree-new-folder"
                                        onSelect={() =>
                                            actions.onNewFolder(child.path)
                                        }
                                    >
                                        <FolderPlus />
                                        New folder
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        {!isCollapsed && (
                            <FolderContents
                                folder={child}
                                depth={depth + 1}
                                vault={vault}
                                activeNoteId={activeNoteId}
                                collapsed={collapsed}
                                onToggle={onToggle}
                                actions={actions}
                            />
                        )}
                    </li>
                );
            })}
            {folder.notes.map((note) => (
                <li key={note.id}>
                    <Link
                        href={show({ vault: vault.id, note: note.id })}
                        preserveScroll
                        aria-current={
                            note.id === activeNoteId ? 'page' : undefined
                        }
                        className={cn(
                            'flex items-center gap-1.5 rounded-md py-1 pr-2 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            note.id === activeNoteId &&
                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                        )}
                        style={{ paddingLeft: `${depth * 12 + 28}px` }}
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
