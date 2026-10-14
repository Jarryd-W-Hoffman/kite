import { Head, Link, router } from '@inertiajs/react';
import { FileText, Hash } from 'lucide-react';
import { useState } from 'react';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { cn } from '@/lib/utils';
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
    folders,
    colour_groups: colourGroups,
    tags,
    indexing,
    watching,
    tag,
    notes,
}: Props) {
    const [paletteOpen, setPaletteOpen] = useState(false);

    useVaultSync(
        vault,
        () =>
            router.reload({
                only: ['notes', 'tree', 'folders', 'colour_groups', 'tags'],
            }),
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
                    folders={folders}
                    colourGroups={colourGroups}
                    tags={tags}
                    activeNoteId={null}
                    activeTag={tag}
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-4 text-[13px] text-muted-foreground">
                        <Hash className="size-3.5" />
                        Tags
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {notes.length === 0 ? (
                            <EmptyState
                                icon={Hash}
                                title={`No notes are tagged #${tag}`}
                                description="Add the tag anywhere in a note, or in its frontmatter tags list."
                                className="h-full"
                            />
                        ) : (
                            <div className="mx-auto max-w-3xl px-8 pt-14 pb-24">
                                <div className="mb-8 flex items-end gap-4">
                                    <span className="flex size-14 items-center justify-center rounded-2xl bg-kite-accent-soft text-primary">
                                        <Hash className="size-7" />
                                    </span>
                                    <div>
                                        <h1 className="text-3xl font-bold tracking-tight">
                                            {tag}
                                        </h1>
                                        <p className="text-sm text-muted-foreground tabular-nums">
                                            {notes.length}{' '}
                                            {notes.length === 1
                                                ? 'note'
                                                : 'notes'}
                                        </p>
                                    </div>
                                </div>
                                <ul
                                    className="grid gap-2"
                                    data-test="tagged-notes"
                                >
                                    {notes.map((note) => (
                                        <li
                                            key={note.id}
                                            className="group/card rounded-xl border bg-card p-4 shadow-kite-sm transition-all hover:-translate-y-px hover:border-primary/30 hover:shadow-kite-lg"
                                        >
                                            <Link
                                                href={show({
                                                    vault: vault.id,
                                                    note: note.id,
                                                })}
                                                className="group flex items-start gap-3"
                                            >
                                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border bg-background text-muted-foreground transition-colors group-hover/card:text-primary">
                                                    <FileText className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-semibold">
                                                        {note.title}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {note.path} ·{' '}
                                                        {new Date(
                                                            note.modified_at *
                                                                1000,
                                                        ).toLocaleDateString()}
                                                    </div>
                                                </div>
                                            </Link>
                                            <div className="mt-2.5 ml-11 flex flex-wrap gap-1">
                                                {note.tags.map((name) => (
                                                    <Link
                                                        key={name}
                                                        href={showTag({
                                                            vault: vault.id,
                                                            tag: name,
                                                        })}
                                                        className={cn(
                                                            'rounded-md px-1.5 py-0.5 text-xs transition-colors',
                                                            name === tag
                                                                ? 'bg-kite-accent-soft font-medium text-primary'
                                                                : 'bg-muted text-muted-foreground hover:text-foreground',
                                                        )}
                                                    >
                                                        #{name}
                                                    </Link>
                                                ))}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
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
