import { Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    CheckSquare,
    ChevronDown,
    ChevronUp,
    Clock,
    ChevronsUpDown,
    FolderOpen,
    Import,
    FolderPlus,
    Hash,
    Keyboard,
    LayoutTemplate,
    Monitor,
    Moon,
    Network,
    Plus,
    Search,
    Settings2,
    Star,
    Sun,
    Presentation,
    SearchCheck,
    Table2,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { DailyCalendar } from '@/components/kite/daily-calendar';
import { FileTree } from '@/components/kite/file-tree';
import { FolderDialogs } from '@/components/kite/folder-dialogs';
import type { FolderDialogState } from '@/components/kite/folder-dialogs';
import { IndexingStatus } from '@/components/kite/indexing-status';
import { Kbd } from '@/components/kite/kbd';
import { NotionImportDialog } from '@/components/kite/notion-import-dialog';
import { PreferencesDialog } from '@/components/kite/preferences-dialog';
import { ShortcutsDialog } from '@/components/kite/shortcuts-dialog';
import { TemplateDialog } from '@/components/kite/template-dialog';
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
import { booleanCodec, useStoredState } from '@/lib/storage';
import { cn } from '@/lib/utils';
import { show as showCanvas, store as storeCanvas } from '@/routes/canvases';
import { store as openDaily } from '@/routes/daily-notes';
import { show as showNote } from '@/routes/notes';
import { store } from '@/routes/notes';
import {
    show as showSearch,
    store as storeSearch,
} from '@/routes/saved-searches';
import { show as showView, store as storeView } from '@/routes/table-views';
import { index as tasksIndex } from '@/routes/tasks';
import { show as showGraph } from '@/routes/vault-graph';
import { show as showTag } from '@/routes/vault-tags';
import { index as vaultsIndex, show as showVault } from '@/routes/vaults';
import type {
    IndexingProgress,
    NoteLinkSummary,
    NoteTemplate,
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
    tasksActive?: boolean;
    activeViewId?: number | null;
    activeCanvasPath?: string | null;
    activeSearchId?: number | null;
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
    tasksActive = false,
    activeViewId = null,
    activeCanvasPath = null,
    activeSearchId = null,
    activeTag = null,
    onSearch,
}: Props) {
    const { kite } = usePage().props;
    const [preferencesOpen, setPreferencesOpen] = useState(false);
    const [shortcutsOpen, setShortcutsOpen] = useState(false);
    const [importing, setImporting] = useState(false);
    const [calendarOpen, setCalendarOpen] = useStoredState(
        'kite:calendar-open',
        booleanCodec,
        true,
    );
    const [templateFolder, setTemplateFolder] = useState<string | null>(null);
    const {
        templates = [],
        open_tasks: openTasks = 0,
        table_views: tableViews = [],
        canvases = [],
        saved_searches: savedSearches = [],
        favourites = [],
        recent_notes: recentNotes = [],
        daily_dates: dailyDates = [],
        note: openNote,
    } = usePage().props as {
        templates?: NoteTemplate[];
        open_tasks?: number;
        table_views?: { id: number; name: string }[];
        canvases?: { path: string; name: string }[];
        saved_searches?: { id: number; name: string }[];
        favourites?: NoteLinkSummary[];
        recent_notes?: NoteLinkSummary[];
        daily_dates?: string[];
        note?: { daily_date?: string | null } | null;
    };
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
        'new-from-template': () => setTemplateFolder(''),
        tasks: () => router.visit(tasksIndex(vault.id)),
        'new-view': () => router.post(storeView(vault.id).url),
        'new-canvas': () => router.post(storeCanvas(vault.id).url),
        'new-search': () => router.post(storeSearch(vault.id).url),
        search: onSearch,
        graph: () => router.visit(showGraph(vault.id)),
        'toggle-theme': () =>
            updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark'),
    });

    return (
        <aside className="flex h-full w-64 shrink-0 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
            <div
                className={cn(
                    'kite-drag flex h-12 shrink-0 items-center gap-1 px-2',
                    insetTrafficLights && 'pl-20',
                )}
            >
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="kite-no-drag group flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-1.5 py-1.5 text-left text-sm font-semibold transition-colors hover:bg-sidebar-accent"
                        >
                            <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-gradient-to-br from-primary to-[oklch(0.62_0.2_310)] text-[11px] font-bold text-primary-foreground shadow-kite-sm">
                                {vault.name.trim().charAt(0).toUpperCase() ||
                                    'K'}
                            </span>
                            <span className="truncate text-sidebar-accent-foreground">
                                {vault.name}
                            </span>
                            <ChevronsUpDown className="ml-auto size-3.5 shrink-0 text-muted-foreground opacity-60 transition-opacity group-hover:opacity-100" />
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
                        <DropdownMenuItem
                            onSelect={() => setImporting(true)}
                            data-test="import-notion"
                        >
                            <Import />
                            Import from Notion…
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <nav className="space-y-px px-2 pb-2" aria-label="Workspace">
                <NavItem
                    icon={Search}
                    label="Search"
                    shortcut="Mod+K"
                    onClick={onSearch}
                />
                <NavItem
                    icon={CalendarDays}
                    label="Today"
                    shortcut="Mod+D"
                    onClick={() => openDailyNote(vault)}
                    testId="today-link"
                />
                <NavItem
                    icon={CheckSquare}
                    label="Tasks"
                    href={tasksIndex(vault.id).url}
                    active={tasksActive}
                    badge={openTasks}
                    testId="tasks-link"
                />
                <NavItem
                    icon={Network}
                    label="Graph view"
                    shortcut="Mod+G"
                    href={showGraph(vault.id).url}
                    active={graphActive}
                    testId="graph-link"
                />
            </nav>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-4">
                <div className="mb-3">
                    <SectionHeading title="Calendar">
                        <SectionAction
                            label={
                                calendarOpen ? 'Hide calendar' : 'Show calendar'
                            }
                            onClick={() => setCalendarOpen(!calendarOpen)}
                            testId="toggle-calendar"
                        >
                            {calendarOpen ? <ChevronUp /> : <ChevronDown />}
                        </SectionAction>
                    </SectionHeading>
                    {calendarOpen && (
                        <DailyCalendar
                            dates={dailyDates}
                            activeDate={openNote?.daily_date ?? null}
                            onOpen={(date) => openDailyNote(vault, date)}
                        />
                    )}
                </div>

                {favourites.length > 0 && (
                    <div className="mb-4" data-test="favourites">
                        <SectionHeading title="Favourites" />
                        {favourites.map((item) => (
                            <NavItem
                                key={item.id}
                                icon={Star}
                                label={item.title}
                                href={
                                    showNote({ vault: vault.id, note: item.id })
                                        .url
                                }
                                active={item.id === activeNoteId}
                                testId="favourite-link"
                            />
                        ))}
                    </div>
                )}

                {recentNotes.length > 0 && (
                    <div className="mb-4" data-test="recent-notes">
                        <SectionHeading title="Recent" />
                        {recentNotes.map((item) => (
                            <NavItem
                                key={item.id}
                                icon={Clock}
                                label={item.title}
                                href={
                                    showNote({ vault: vault.id, note: item.id })
                                        .url
                                }
                                active={item.id === activeNoteId}
                                testId="recent-link"
                            />
                        ))}
                    </div>
                )}

                <SectionHeading title="Notes">
                    <SectionAction
                        label="New note"
                        shortcut={formatShortcut('Mod+N', kite.platform)}
                        onClick={() => createNote(vault)}
                    >
                        <Plus />
                    </SectionAction>
                    <SectionAction
                        label="New note from template"
                        shortcut={formatShortcut('Mod+Shift+N', kite.platform)}
                        onClick={() => setTemplateFolder('')}
                        testId="new-from-template"
                    >
                        <LayoutTemplate />
                    </SectionAction>
                    <SectionAction
                        label="New folder"
                        onClick={() =>
                            setFolderDialog({ kind: 'new', path: '' })
                        }
                        testId="new-folder"
                    >
                        <FolderPlus />
                    </SectionAction>
                </SectionHeading>
                <FileTree
                    vault={vault}
                    notes={tree}
                    folders={folders}
                    groups={kite.tree_group_colours ? colourGroups : []}
                    activeNoteId={activeNoteId}
                    onNewNote={(folder) =>
                        createNote(vault, 'Untitled', folder)
                    }
                    onNewFromTemplate={setTemplateFolder}
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

                <div className="mt-5">
                    <SectionHeading title="Views">
                        <SectionAction
                            label="New view"
                            onClick={() => router.post(storeView(vault.id).url)}
                            testId="new-view"
                        >
                            <Plus />
                        </SectionAction>
                    </SectionHeading>
                    {tableViews.map((view) => (
                        <NavItem
                            key={view.id}
                            icon={Table2}
                            label={view.name}
                            href={
                                showView({
                                    vault: vault.id,
                                    tableView: view.id,
                                }).url
                            }
                            active={view.id === activeViewId}
                            testId="view-link"
                        />
                    ))}
                </div>

                <div className="mt-5">
                    <SectionHeading title="Searches">
                        <SectionAction
                            label="New saved search"
                            onClick={() =>
                                router.post(storeSearch(vault.id).url)
                            }
                            testId="new-search"
                        >
                            <Plus />
                        </SectionAction>
                    </SectionHeading>
                    {savedSearches.map((search) => (
                        <NavItem
                            key={search.id}
                            icon={SearchCheck}
                            label={search.name}
                            href={
                                showSearch({
                                    vault: vault.id,
                                    savedSearch: search.id,
                                }).url
                            }
                            active={search.id === activeSearchId}
                            testId="search-link"
                        />
                    ))}
                </div>

                <div className="mt-5">
                    <SectionHeading title="Canvases">
                        <SectionAction
                            label="New canvas"
                            onClick={() =>
                                router.post(storeCanvas(vault.id).url)
                            }
                            testId="new-canvas"
                        >
                            <Plus />
                        </SectionAction>
                    </SectionHeading>
                    {canvases.map((canvas) => (
                        <NavItem
                            key={canvas.path}
                            icon={Presentation}
                            label={canvas.name}
                            href={
                                showCanvas({
                                    vault: vault.id,
                                    path: canvas.path,
                                }).url
                            }
                            active={canvas.path === activeCanvasPath}
                            testId="canvas-link"
                        />
                    ))}
                </div>

                {tags.length > 0 && (
                    <div className="mt-5">
                        <SectionHeading title="Tags" />
                        <ul className="flex flex-wrap gap-1 px-1.5">
                            {tags.map((tag) => (
                                <li key={tag.name}>
                                    <Link
                                        href={showTag({
                                            vault: vault.id,
                                            tag: tag.name,
                                        })}
                                        data-test={`tag-${tag.name}`}
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                            tag.name === activeTag &&
                                                'bg-kite-accent-soft text-primary hover:bg-kite-accent-soft hover:text-primary',
                                        )}
                                    >
                                        <Hash className="size-3 opacity-60" />
                                        {tag.name}
                                        <span className="tabular-nums opacity-50">
                                            {tag.count}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
            <div className="flex items-center gap-0.5 border-t border-sidebar-border px-2 py-1.5">
                <button
                    type="button"
                    onClick={() => setPreferencesOpen(true)}
                    data-test="preferences-link"
                    className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
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
            <TemplateDialog
                vault={vault}
                templates={templates}
                folder={templateFolder}
                onClose={() => setTemplateFolder(null)}
            />
            <IndexingStatus vault={vault} indexing={indexing} />
            <NotionImportDialog
                vault={vault}
                open={importing}
                onOpenChange={setImporting}
            />
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

/**
 * A row in the sidebar's top navigation, as a link or a button.
 */
function NavItem({
    icon: Icon,
    label,
    shortcut,
    onClick,
    href,
    active = false,
    badge,
    testId,
}: {
    icon: typeof Search;
    label: string;
    shortcut?: string;
    /** A count shown at the end of the row, e.g. open tasks. */
    badge?: number;
    onClick?: () => void;
    href?: string;
    active?: boolean;
    testId?: string;
}) {
    const className = cn(
        'group flex w-full items-center gap-2.5 rounded-md px-2 py-[5px] text-[13.5px] text-sidebar-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
        active &&
            'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
    );
    const contents = (
        <>
            <Icon className="size-4 shrink-0 text-muted-foreground group-hover:text-sidebar-accent-foreground" />
            <span className="min-w-0 truncate">{label}</span>
            {badge !== undefined && badge > 0 && (
                <span className="ml-auto rounded-full bg-sidebar-accent px-1.5 text-[11px] font-medium text-muted-foreground tabular-nums group-hover:bg-background">
                    {badge}
                </span>
            )}
            {shortcut && (
                <Kbd
                    shortcut={shortcut}
                    className="ml-auto border-transparent py-0 opacity-0 transition-opacity group-hover:opacity-100"
                />
            )}
        </>
    );

    return href ? (
        <Link href={href} className={className} data-test={testId}>
            {contents}
        </Link>
    ) : (
        <button
            type="button"
            onClick={onClick}
            className={className}
            data-test={testId}
        >
            {contents}
        </button>
    );
}

function SectionHeading({
    title,
    children,
}: {
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="group/section flex h-7 items-center gap-0.5 pr-0.5 pl-2">
            <h2 className="flex-1 text-[11px] font-semibold tracking-wider text-muted-foreground/80 uppercase">
                {title}
            </h2>
            {children}
        </div>
    );
}

function SectionAction({
    label,
    shortcut,
    onClick,
    testId,
    children,
}: {
    label: string;
    shortcut?: string;
    onClick: () => void;
    testId?: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-6 text-muted-foreground opacity-60 transition-opacity group-hover/section:opacity-100 focus-visible:opacity-100 [&_svg]:size-3.5"
                    aria-label={label}
                    data-test={testId}
                    onClick={onClick}
                >
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {label}
                {shortcut ? ` (${shortcut})` : ''}
            </TooltipContent>
        </Tooltip>
    );
}
