import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronsUpDown,
    FolderOpen,
    FolderPlus,
    Hash,
    Keyboard,
    Monitor,
    Moon,
    Network,
    Plus,
    Search,
    Settings2,
    Sun,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { FileTree } from '@/components/kite/file-tree';
import { FolderDialogs } from '@/components/kite/folder-dialogs';
import type { FolderDialogState } from '@/components/kite/folder-dialogs';
import { IndexingStatus } from '@/components/kite/indexing-status';
import { Kbd } from '@/components/kite/kbd';
import { PreferencesDialog } from '@/components/kite/preferences-dialog';
import { ShortcutsDialog } from '@/components/kite/shortcuts-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppCommands } from '@/hooks/use-app-commands';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import type { ColourGroup } from '@/lib/colour-groups';
import { formatShortcut } from '@/lib/shortcuts';
import { cn } from '@/lib/utils';
import { store as openDaily } from '@/routes/daily-notes';
import { store } from '@/routes/notes';
import { show as showGraph } from '@/routes/vault-graph';
import { show as showTag } from '@/routes/vault-tags';
import { index as vaultsIndex, show as showVault } from '@/routes/vaults';
import type {
    IndexingProgress,
    TagCount,
    TreeNote,
    VaultSummary,
} from '@/types';

type Props = {
    vault: VaultSummary;
    vaults: VaultSummary[];
    tree: TreeNote[];
    folders: string[];
    colourGroups: ColourGroup[];
    tags: TagCount[];
    activeNoteId: number | null;
    indexing?: IndexingProgress | null;
    graphActive?: boolean;
    activeTag?: string | null;
    onSearch: () => void;
};

export function createNote(
    vault: VaultSummary,
    title = 'Untitled',
    folder?: string,
): void {
    router.post(store(vault.id).url, folder ? { title, folder } : { title });
}

export function openDailyNote(vault: VaultSummary, date?: string): void {
    router.post(openDaily(vault.id).url, date ? { date } : {});
}

export function WorkspaceSidebar({
    vault,
    vaults,
    tree,
    folders,
    colourGroups,
    tags,
    activeNoteId,
    indexing = null,
    graphActive = false,
    activeTag = null,
    onSearch,
}: Props) {
    const { kite } = usePage().props;
    const [preferencesOpen, setPreferencesOpen] = useState(false);
    const [shortcutsOpen, setShortcutsOpen] = useState(false);
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const [folderDialog, setFolderDialog] = useState<FolderDialogState>(null);
    const notesIn = (folder: string) =>
        tree.filter((note) => note.path.startsWith(`${folder}/`));
    const insetTrafficLights = kite.native && kite.platform === 'darwin';

    useAppCommands({
        'new-note': () => createNote(vault),
        'daily-note': () => openDailyNote(vault),
        'open-vault': () => router.visit(vaultsIndex()),
        preferences: () => setPreferencesOpen(true),
        shortcuts: () => setShortcutsOpen(true),
        search: onSearch,
        graph: () => router.visit(showGraph(vault.id)),
        'toggle-theme': () =>
            updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark'),
    });

    return (
        <aside className="flex h-full w-64 shrink-0 flex-col border-r bg-sidebar text-sidebar-foreground">
            <div
                className={cn(
                    'kite-drag flex h-12 shrink-0 items-center gap-1 border-b px-2',
                    insetTrafficLights && 'pl-20',
                )}
            >
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="kite-no-drag flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm font-semibold hover:bg-sidebar-accent"
                        >
                            <AppLogoIcon className="size-4 shrink-0 fill-current" />
                            <span className="truncate">{vault.name}</span>
                            <ChevronsUpDown className="ml-auto size-3.5 shrink-0 text-muted-foreground" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-64">
                        <DropdownMenuLabel>Vaults</DropdownMenuLabel>
                        {vaults.map((item) => (
                            <DropdownMenuItem key={item.id} asChild>
                                <Link href={showVault(item.id)}>
                                    <span className="truncate">
                                        {item.name}
                                    </span>
                                    {item.id === vault.id && (
                                        <Check className="ml-auto" />
                                    )}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href={vaultsIndex()}>
                                <FolderOpen />
                                Open another vault…
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="kite-no-drag size-8"
                            aria-label="New note"
                            onClick={() => createNote(vault)}
                        >
                            <Plus />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        New note ({formatShortcut('Mod+N', kite.platform)})
                    </TooltipContent>
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="kite-no-drag size-8"
                            aria-label="New folder"
                            data-test="new-folder"
                            onClick={() =>
                                setFolderDialog({ kind: 'new', path: '' })
                            }
                        >
                            <FolderPlus />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>New folder</TooltipContent>
                </Tooltip>
            </div>

            <div className="space-y-1 p-2">
                <button
                    type="button"
                    onClick={onSearch}
                    className="flex w-full items-center gap-2 rounded-md border bg-background px-2.5 py-1.5 text-sm text-muted-foreground hover:bg-accent"
                >
                    <Search className="size-4" />
                    Search
                    <Kbd shortcut="Mod+K" className="ml-auto py-0" />
                </button>
                <button
                    type="button"
                    onClick={() => openDailyNote(vault)}
                    data-test="today-link"
                    className="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-sm hover:bg-sidebar-accent"
                >
                    <CalendarDays className="size-4" />
                    Today
                    <Kbd shortcut="Mod+D" className="ml-auto py-0" />
                </button>
                <Link
                    href={showGraph(vault.id)}
                    data-test="graph-link"
                    className={cn(
                        'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-sm hover:bg-sidebar-accent',
                        graphActive &&
                            'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                    )}
                >
                    <Network className="size-4" />
                    Graph view
                    <Kbd shortcut="Mod+G" className="ml-auto py-0" />
                </Link>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-4">
                <FileTree
                    vault={vault}
                    notes={tree}
                    folders={folders}
                    groups={kite.tree_group_colours ? colourGroups : []}
                    activeNoteId={activeNoteId}
                    onNewNote={(folder) =>
                        createNote(vault, 'Untitled', folder)
                    }
                    onNewFolder={(path) =>
                        setFolderDialog({ kind: 'new', path })
                    }
                    onRenameFolder={(path) =>
                        setFolderDialog({ kind: 'rename', path })
                    }
                    onDeleteFolder={(path) =>
                        setFolderDialog({ kind: 'delete', path })
                    }
                />

                {tags.length > 0 && (
                    <div className="mt-6">
                        <h2 className="px-2 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Tags
                        </h2>
                        <ul className="flex flex-wrap gap-1 px-2">
                            {tags.map((tag) => (
                                <li key={tag.name}>
                                    <Link
                                        href={showTag({
                                            vault: vault.id,
                                            tag: tag.name,
                                        })}
                                        data-test={`tag-${tag.name}`}
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-full bg-sidebar-accent px-2 py-0.5 text-xs hover:ring-1 hover:ring-ring',
                                            tag.name === activeTag &&
                                                'bg-primary text-primary-foreground',
                                        )}
                                    >
                                        <Hash className="size-3" />
                                        {tag.name}
                                        <span className="tabular-nums opacity-60">
                                            {tag.count}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
            <div className="flex items-center gap-1 border-t p-2">
                <button
                    type="button"
                    onClick={() => setPreferencesOpen(true)}
                    data-test="preferences-link"
                    className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2.5 py-1.5 text-sm text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <Settings2 className="size-4" />
                    Preferences
                </button>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 shrink-0 text-muted-foreground"
                            aria-label="Keyboard shortcuts"
                            data-test="shortcuts-link"
                            onClick={() => setShortcutsOpen(true)}
                        >
                            <Keyboard className="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="top">
                        Keyboard shortcuts (
                        {formatShortcut('Mod+/', kite.platform)})
                    </TooltipContent>
                </Tooltip>
                <ThemeButton
                    appearance={appearance}
                    onChange={updateAppearance}
                />
            </div>
            <FolderDialogs
                vault={vault}
                state={folderDialog}
                noteCount={(folder) => notesIn(folder).length}
                containsActiveNote={(folder) =>
                    notesIn(folder).some((note) => note.id === activeNoteId)
                }
                onClose={() => setFolderDialog(null)}
            />
            <PreferencesDialog
                open={preferencesOpen}
                onOpenChange={setPreferencesOpen}
            />
            <ShortcutsDialog
                open={shortcutsOpen}
                onOpenChange={setShortcutsOpen}
            />
            <IndexingStatus vault={vault} indexing={indexing} />
        </aside>
    );
}

const THEMES: Record<
    Appearance,
    { label: string; icon: typeof Sun; next: Appearance }
> = {
    light: { label: 'Light', icon: Sun, next: 'dark' },
    dark: { label: 'Dark', icon: Moon, next: 'system' },
    system: { label: 'System', icon: Monitor, next: 'light' },
};

/**
 * Cycles light, dark and system themes from the sidebar footer.
 */
function ThemeButton({
    appearance,
    onChange,
}: {
    appearance: Appearance;
    onChange: (appearance: Appearance) => void;
}) {
    const { label, icon: Icon, next } = THEMES[appearance];

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 text-muted-foreground"
                    aria-label={`Theme: ${label}. Switch to ${THEMES[next].label.toLowerCase()}`}
                    data-test="theme-toggle"
                    data-appearance={appearance}
                    onClick={() => onChange(next)}
                >
                    <Icon className="size-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent side="top">Theme: {label}</TooltipContent>
        </Tooltip>
    );
}
