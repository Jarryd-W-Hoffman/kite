import { Link } from '@inertiajs/react';
import { ChevronRight, FileText, Folder, FolderOpen } from 'lucide-react';
import { useMemo, useState } from 'react';
import { buildTree, noteName } from '@/lib/note-tree';
import type { TreeFolder } from '@/lib/note-tree';
import { cn } from '@/lib/utils';
import { show } from '@/routes/notes';
import type { TreeNote, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    notes: TreeNote[];
    activeNoteId: number | null;
};

function storageKey(vault: VaultSummary): string {
    return `kite:collapsed:${vault.id}`;
}

export function FileTree({ vault, notes, activeNoteId }: Props) {
    const tree = useMemo(() => buildTree(notes), [notes]);
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

    const toggle = (path: string) => {
        setCollapsed((current) => {
            const next = new Set(current);

            if (next.has(path)) {
                next.delete(path);
            } else {
                next.add(path);
            }

            localStorage.setItem(storageKey(vault), JSON.stringify([...next]));

            return next;
        });
    };

    if (notes.length === 0) {
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
                onToggle={toggle}
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
};

function FolderContents({
    folder,
    depth,
    vault,
    activeNoteId,
    collapsed,
    onToggle,
}: FolderContentsProps) {
    const indent = { paddingLeft: `${depth * 12 + 8}px` };

    return (
        <ul role="group">
            {folder.folders.map((child) => {
                const isCollapsed = collapsed.has(child.path);

                return (
                    <li key={child.path}>
                        <button
                            type="button"
                            onClick={() => onToggle(child.path)}
                            aria-expanded={!isCollapsed}
                            className="flex w-full items-center gap-1.5 rounded-md py-1 pr-2 text-left text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
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
                        {!isCollapsed && (
                            <FolderContents
                                folder={child}
                                depth={depth + 1}
                                vault={vault}
                                activeNoteId={activeNoteId}
                                collapsed={collapsed}
                                onToggle={onToggle}
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
