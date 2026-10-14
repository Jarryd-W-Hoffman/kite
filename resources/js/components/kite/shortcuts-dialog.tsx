import { Kbd } from '@/components/kite/kbd';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const SECTIONS: { title: string; shortcuts: [string, string][] }[] = [
    {
        title: 'General',
        shortcuts: [
            ['Mod+K', 'Search notes'],
            ['Mod+N', 'New note'],
            ['Mod+D', "Today's note"],
            ['Mod+G', 'Graph view'],
            ['Mod+O', 'Open another vault'],
            ['Mod+,', 'Preferences'],
            ['Mod+Shift+L', 'Toggle dark mode'],
            ['Mod+/', 'Keyboard shortcuts'],
        ],
    },
    {
        title: 'Tabs and views',
        shortcuts: [
            ['Ctrl+Tab', 'Next tab'],
            ['Ctrl+Shift+Tab', 'Previous tab'],
            ['Mod+W', 'Close tab'],
            ['Mod+E', 'Toggle preview'],
        ],
    },
    {
        title: 'Editor',
        shortcuts: [
            ['Mod+S', 'Save now'],
            ['Mod+B', 'Bold'],
            ['Mod+I', 'Italic'],
            ['Mod+F', 'Find and replace'],
            ['Mod+Z', 'Undo'],
            ['Mod+Shift+Z', 'Redo'],
            ['Mod+Alt+M', 'Comment on the selection'],
            ['[[', 'Link to a note'],
        ],
    },
    {
        title: 'Links and search',
        shortcuts: [
            ['Mod+Click', 'Open a link in a new tab'],
            ['Mod+Shift+Click', 'Open a link beside this note'],
            ['Shift+Enter', 'Open a search result beside this note'],
            ['Mod+Enter', 'Create a note from the search text'],
        ],
    },
];

/**
 * Every keyboard shortcut in one place, written for the current platform.
 */
export function ShortcutsDialog({ open, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag max-h-[85vh] overflow-y-auto sm:max-w-2xl"
                data-test="shortcuts-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Keyboard shortcuts</DialogTitle>
                    <DialogDescription>
                        Most of these are also in the app menu.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                    {SECTIONS.map((section) => (
                        <section key={section.title} className="space-y-2">
                            <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {section.title}
                            </h3>
                            <dl className="space-y-1.5 text-sm">
                                {section.shortcuts.map(([shortcut, action]) => (
                                    <div
                                        key={shortcut}
                                        className="flex items-center justify-between gap-4"
                                    >
                                        <dt>{action}</dt>
                                        <dd>
                                            <Kbd shortcut={shortcut} />
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
