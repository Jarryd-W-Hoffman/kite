import {
    CalendarDays,
    CheckSquare,
    Code2,
    FileDown,
    FilePlus2,
    FolderOpen,
    Globe,
    History,
    KeyRound,
    Keyboard,
    LayoutTemplate,
    MessageSquare,
    Moon,
    Network,
    PanelRight,
    Settings2,
    Presentation,
    SearchCheck,
    Star,
    Table2,
    X,
    Eye,
    Focus,
    LockKeyhole,
    Merge,
    Scissors,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { AppCommand } from '@/hooks/use-app-commands';

export type PaletteCommand = {
    id: AppCommand;
    label: string;
    icon: LucideIcon;
    shortcut?: string;
    /** Only offered while a note is open. */
    note?: boolean;
    /** Only offered in the desktop app. */
    desktop?: boolean;
    keywords?: string;
};

/**
 * Everything the command palette can run after typing ">".
 */
export const PALETTE_COMMANDS: PaletteCommand[] = [
    {
        id: 'new-note',
        label: 'New note',
        icon: FilePlus2,
        shortcut: 'Mod+N',
        keywords: 'create add',
    },
    {
        id: 'new-from-template',
        label: 'New note from template',
        icon: LayoutTemplate,
        shortcut: 'Mod+Shift+N',
    },
    {
        id: 'daily-note',
        label: 'Open today’s note',
        icon: CalendarDays,
        shortcut: 'Mod+D',
        keywords: 'daily journal',
    },
    {
        id: 'tasks',
        label: 'Open tasks',
        icon: CheckSquare,
        keywords: 'todo checklist',
    },
    { id: 'graph', label: 'Open graph view', icon: Network, shortcut: 'Mod+G' },
    {
        id: 'new-view',
        label: 'New table view',
        icon: Table2,
        keywords: 'database base',
    },
    {
        id: 'new-canvas',
        label: 'New canvas',
        icon: Presentation,
        keywords: 'whiteboard board diagram map',
    },
    {
        id: 'new-search',
        label: 'New saved search',
        icon: SearchCheck,
        keywords: 'filter query smart folder',
    },
    {
        id: 'toggle-theme',
        label: 'Toggle dark mode',
        icon: Moon,
        shortcut: 'Mod+Shift+L',
        keywords: 'theme light appearance',
    },
    {
        id: 'preferences',
        label: 'Open preferences',
        icon: Settings2,
        shortcut: 'Mod+,',
        keywords: 'settings options',
    },
    {
        id: 'shortcuts',
        label: 'Show keyboard shortcuts',
        icon: Keyboard,
        shortcut: 'Mod+/',
        keywords: 'keys help',
    },
    {
        id: 'open-vault',
        label: 'Open another vault',
        icon: FolderOpen,
        shortcut: 'Mod+O',
        keywords: 'switch',
    },
    {
        id: 'toggle-preview',
        label: 'Toggle preview',
        icon: Eye,
        shortcut: 'Mod+E',
        note: true,
        keywords: 'reading view',
    },
    {
        id: 'focus-mode',
        label: 'Toggle focus mode',
        icon: Focus,
        shortcut: 'Mod+Shift+F',
        note: true,
        keywords: 'zen distraction free writing typewriter goal',
    },
    {
        id: 'toggle-star',
        label: 'Add or remove from favourites',
        icon: Star,
        note: true,
        keywords: 'star bookmark pin',
    },
    {
        id: 'toggle-comments',
        label: 'Toggle comments',
        icon: MessageSquare,
        note: true,
        keywords: 'annotations',
    },
    {
        id: 'toggle-panel',
        label: 'Toggle details panel',
        icon: PanelRight,
        note: true,
        keywords: 'backlinks outline properties sidebar',
    },
    {
        id: 'version-history',
        label: 'Show version history',
        icon: History,
        note: true,
        keywords: 'revisions restore diff',
    },
    {
        id: 'extract-selection',
        label: 'Extract selection to a new note',
        icon: Scissors,
        note: true,
        keywords: 'refactor split move',
    },
    {
        id: 'merge-note',
        label: 'Merge into another note…',
        icon: Merge,
        note: true,
        keywords: 'refactor combine join',
    },
    {
        id: 'lock-note',
        label: 'Lock or unlock this note',
        icon: LockKeyhole,
        note: true,
        keywords: 'encrypt encryption password secure private',
    },
    {
        id: 'lock-vault',
        label: 'Lock the vault now',
        icon: LockKeyhole,
        note: true,
        keywords: 'encrypt close secure',
    },
    {
        id: 'change-passphrase',
        label: 'Change the vault passphrase…',
        icon: KeyRound,
        note: true,
        keywords: 'password locked notes encrypt',
    },
    {
        id: 'recovery-key',
        label: 'New recovery key…',
        icon: KeyRound,
        note: true,
        keywords: 'forgot passphrase password reset locked notes',
    },
    {
        id: 'export-html',
        label: 'Export as HTML',
        icon: Code2,
        note: true,
        keywords: 'download save',
    },
    {
        id: 'export-pdf',
        label: 'Export as PDF',
        icon: FileDown,
        note: true,
        desktop: true,
        keywords: 'download print',
    },
    {
        id: 'publish',
        label: 'Publish as a web page',
        icon: Globe,
        note: true,
        keywords: 'share site',
    },
    {
        id: 'close-tab',
        label: 'Close tab',
        icon: X,
        shortcut: 'Mod+W',
        note: true,
    },
];

/**
 * Commands that match every word typed, in their listed order.
 */
export function matchCommands(
    query: string,
    { noteOpen, desktop }: { noteOpen: boolean; desktop: boolean },
): PaletteCommand[] {
    const words = query.toLowerCase().split(/\s+/).filter(Boolean);

    return PALETTE_COMMANDS.filter(
        (command) =>
            (noteOpen || !command.note) &&
            (desktop || !command.desktop) &&
            words.every((word) =>
                `${command.label} ${command.keywords ?? ''}`
                    .toLowerCase()
                    .includes(word),
            ),
    );
}
