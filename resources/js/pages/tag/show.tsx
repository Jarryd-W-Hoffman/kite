import { Head, Link, router } from '@inertiajs/react';
import { FileText, Hash } from 'lucide-react';
import { useState } from 'react';
import { CommandPalette } from '@/components/kite/command-palette';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { show } from '@/routes/notes';
import { show as showTag } from '@/routes/vault-tags';
import type { WorkspaceProps } from '@/types';

type TaggedNote = {
    id: number;
    title: string;
    path: string;
    modified_at: number;
    tags: string[];
};

type Props = WorkspaceProps & {
    tag: string;
    notes: TaggedNote[];
};

export default function TagShow({
    vault,
    vaults,
    tree,
    tags,
    indexing,
    watching,
    tag,
    notes,
}: Props) {
    const [paletteOpen, setPaletteOpen] = useState(false);

    useVaultSync(
        vault,
        () => router.reload({ only: ['notes', 'tree', 'tags'] }),
        watching,
    );

    return (
        <>
            <Head title={`#${tag} · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    tags={tags}
                    activeNoteId={null}
                    activeTag={tag}
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b px-4">
                        <Hash className="size-4 text-muted-foreground" />
                        <h1 className="text-sm font-medium">{tag}</h1>
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {notes.length}{' '}
                            {notes.length === 1 ? 'note' : 'notes'}
                        </span>
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {notes.length === 0 ? (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No notes are tagged #{tag}.
                            </p>
                        ) : (
                            <ul
                                className="mx-auto max-w-3xl divide-y px-6 py-4"
                                data-test="tagged-notes"
                            >
                                {notes.map((note) => (
                                    <li key={note.id} className="py-3">
                                        <Link
                                            href={show({
                                                vault: vault.id,
                                                note: note.id,
                                            })}
                                            className="group flex items-start gap-3"
                                        >
                                            <FileText className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate font-medium group-hover:underline">
                                                    {note.title}
                                                </div>
                                                <div className="truncate text-xs text-muted-foreground">
                                                    {note.path} ·{' '}
                                                    {new Date(
                                                        note.modified_at * 1000,
                                                    ).toLocaleDateString()}
                                                </div>
                                            </div>
                                        </Link>
                                        <div className="mt-1.5 ml-7 flex flex-wrap gap-1">
                                            {note.tags.map((name) => (
                                                <Link
                                                    key={name}
                                                    href={showTag({
                                                        vault: vault.id,
                                                        tag: name,
                                                    })}
                                                    className="rounded-full bg-muted px-2 py-0.5 text-xs hover:ring-1 hover:ring-ring"
                                                >
                                                    #{name}
                                                </Link>
                                            ))}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </main>
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />
        </>
    );
}
