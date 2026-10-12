import { Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronsUpDown,
    FolderOpen,
    Hash,
    Network,
    Plus,
    Search,
} from 'lucide-react';
import { useEffect } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { FileTree } from '@/components/kite/file-tree';
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
import { cn } from '@/lib/utils';
import { store } from '@/routes/notes';
import { show as showGraph } from '@/routes/vault-graph';
import { index as vaultsIndex, show as showVault } from '@/routes/vaults';
import type { TagCount, TreeNote, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    vaults: VaultSummary[];
    tree: TreeNote[];
    tags: TagCount[];
    activeNoteId: number | null;
    graphActive?: boolean;
    onSearch: () => void;
};

export function createNote(vault: VaultSummary, title = 'Untitled'): void {
    router.post(store(vault.id).url, { title });
}

export function WorkspaceSidebar({
    vault,
    vaults,
    tree,
    tags,
    activeNoteId,
    graphActive = false,
    onSearch,
}: Props) {
    const { kite } = usePage().props;
    const insetTrafficLights = kite.native && kite.platform === 'darwin';

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'g') {
                event.preventDefault();
                router.visit(showGraph(vault.id));
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [vault.id]);

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
                    <TooltipContent>New note (⌘N)</TooltipContent>
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
                    <kbd className="ml-auto rounded border px-1.5 text-[10px]">
                        ⌘K
                    </kbd>
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
                    <kbd className="ml-auto rounded border px-1.5 text-[10px] text-muted-foreground">
                        ⌘G
                    </kbd>
                </Link>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-4">
                <FileTree
                    vault={vault}
                    notes={tree}
                    activeNoteId={activeNoteId}
                />

                {tags.length > 0 && (
                    <div className="mt-6">
                        <h2 className="px-2 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Tags
                        </h2>
                        <ul className="flex flex-wrap gap-1 px-2">
                            {tags.map((tag) => (
                                <li key={tag.name}>
                                    <span className="inline-flex items-center gap-0.5 rounded-full bg-sidebar-accent px-2 py-0.5 text-xs">
                                        <Hash className="size-3" />
                                        {tag.name}
                                        <span className="text-muted-foreground tabular-nums">
                                            {tag.count}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </aside>
    );
}
