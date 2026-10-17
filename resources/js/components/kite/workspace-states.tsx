import { router } from '@inertiajs/react';
import {
    CalendarDays,
    FilePlus2,
    LoaderCircle,
    LockKeyhole,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/kite/empty-state';
import { Kbd } from '@/components/kite/kbd';
import { LockedNoteScreen } from '@/components/kite/lock-dialogs';
import { TabBar } from '@/components/kite/tab-bar';
import { createNote, openDailyNote } from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import { useVaultSync } from '@/hooks/use-vault-sync';
import type { NoteDetail, WorkspaceProps } from '@/types';

/*
 * What the workspace shows instead of a note: an empty (or still indexing) vault, or a locked
 * note while its vault is locked.
 */

const GETTING_STARTED: { shortcut: string; text: ReactNode }[] = [
    { shortcut: 'Mod+N', text: 'Create a note' },
    { shortcut: 'Mod+K', text: 'Search notes, or create one by name' },
    { shortcut: 'Mod+D', text: "Open today's daily note" },
    { shortcut: 'Mod+G', text: 'See how your notes connect' },
    {
        shortcut: '[[',
        text: 'Link to another note; missing notes are created on click',
    },
    { shortcut: '#', text: 'Tag a note, e.g. #project/kite' },
];

export function EmptyWorkspace({ vault, indexing, watching }: WorkspaceProps) {
    useVaultSync(vault, () => router.reload(), watching);

    return (
        <main className="flex flex-1 flex-col">
            <div className="kite-drag h-12 shrink-0 border-b" />
            <div className="flex flex-1 flex-col items-center justify-center gap-8 overflow-y-auto">
                {indexing ? (
                    <EmptyState
                        icon={LoaderCircle}
                        iconClassName="animate-spin"
                        title={`Indexing ${vault.name}…`}
                        description="Your notes will appear as soon as they are read."
                    />
                ) : (
                    <>
                        <EmptyState
                            icon={FilePlus2}
                            title={`${vault.name} is empty`}
                            description={
                                <>
                                    Create your first note, or add .md files to{' '}
                                    <code className="font-mono break-all">
                                        {vault.path}
                                    </code>
                                    .
                                </>
                            }
                        >
                            <Button onClick={() => createNote(vault, 'Home')}>
                                <FilePlus2 />
                                Create a note
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => openDailyNote(vault)}
                            >
                                <CalendarDays />
                                Today&apos;s note
                            </Button>
                        </EmptyState>
                        <section
                            className="w-full max-w-md rounded-xl border bg-card p-4 shadow-kite-sm"
                            data-test="getting-started"
                        >
                            <h2 className="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Getting started
                            </h2>
                            <ul className="space-y-2 text-sm">
                                {GETTING_STARTED.map((tip) => (
                                    <li
                                        key={tip.shortcut}
                                        className="flex items-center gap-3"
                                    >
                                        <Kbd
                                            shortcut={tip.shortcut}
                                            className="w-14 shrink-0 text-center"
                                        />
                                        <span>{tip.text}</span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </>
                )}
            </div>
        </main>
    );
}

/**
 * A locked note while its vault is locked: its name, and the passphrase field to open it.
 */
export function LockedWorkspace({
    vault,
    note,
}: WorkspaceProps & { note: NoteDetail }) {
    return (
        <main className="flex min-w-0 flex-1 flex-col">
            <TabBar
                vault={vault}
                current={{ kind: 'note', id: note.id, title: note.title }}
            />
            <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-3 text-sm">
                <LockKeyhole className="size-3.5 text-muted-foreground" />
                <h1 className="truncate font-medium">{note.title}</h1>
            </header>
            <LockedNoteScreen
                vault={vault}
                title={note.title}
                onUnlocked={() => router.reload()}
            />
        </main>
    );
}
