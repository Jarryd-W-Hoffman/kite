import { Link, router } from '@inertiajs/react';
import { ArrowUpRight, Hash, Link2, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { show, store } from '@/routes/notes';
import type { Backlink, NoteMeta, OutgoingLink, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    note: NoteMeta;
    backlinks: Backlink[];
    outgoing: OutgoingLink[];
    children?: ReactNode;
};

function formatValue(value: unknown): string {
    if (Array.isArray(value)) {
        return value.map(formatValue).join(', ');
    }

    if (value !== null && typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function Section({
    title,
    count,
    children,
}: {
    title: string;
    count?: number;
    children: ReactNode;
}) {
    return (
        <section className="space-y-2">
            <h2 className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
                {count !== undefined && (
                    <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] tabular-nums">
                        {count}
                    </span>
                )}
            </h2>
            {children}
        </section>
    );
}

export function NotePanel({
    vault,
    note,
    backlinks,
    outgoing,
    children,
}: Props) {
    const properties = Object.entries(note.frontmatter).filter(
        ([key]) => !['title', 'tags', 'tag'].includes(key),
    );

    return (
        <div className="space-y-6 p-4 text-sm">
            {children}

            <Section title="Backlinks" count={backlinks.length}>
                {backlinks.length === 0 ? (
                    <p className="text-muted-foreground">
                        No notes link here yet.
                    </p>
                ) : (
                    <ul className="space-y-1">
                        {backlinks.map((backlink) => (
                            <li key={`${backlink.id}:${backlink.line}`}>
                                <Link
                                    href={show({
                                        vault: vault.id,
                                        note: backlink.id,
                                    })}
                                    className="block rounded-md px-2 py-1.5 hover:bg-accent"
                                >
                                    <span className="font-medium">
                                        {backlink.title}
                                    </span>
                                    {backlink.context !== '' && (
                                        <span className="mt-0.5 line-clamp-2 block text-xs text-muted-foreground">
                                            {backlink.context}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>

            <Section title="Links" count={outgoing.length}>
                {outgoing.length === 0 ? (
                    <p className="text-muted-foreground">
                        Type [[ to link another note.
                    </p>
                ) : (
                    <ul className="space-y-0.5">
                        {outgoing.map((link) =>
                            link.note_id === null ? (
                                <li key={link.target}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.post(store(vault.id).url, {
                                                title: link.target,
                                            })
                                        }
                                        className="flex w-full items-center gap-2 rounded-md px-2 py-1 text-left text-muted-foreground hover:bg-accent"
                                        title="Create this note"
                                    >
                                        <Plus className="size-3.5 shrink-0" />
                                        <span className="truncate border-b border-dashed">
                                            {link.target}
                                        </span>
                                    </button>
                                </li>
                            ) : (
                                <li key={link.target}>
                                    <Link
                                        href={show({
                                            vault: vault.id,
                                            note: link.note_id,
                                        })}
                                        className="flex items-center gap-2 rounded-md px-2 py-1 hover:bg-accent"
                                    >
                                        <Link2 className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span className="truncate">
                                            {link.title ?? link.target}
                                        </span>
                                        <ArrowUpRight className="ml-auto size-3 shrink-0 text-muted-foreground" />
                                    </Link>
                                </li>
                            ),
                        )}
                    </ul>
                )}
            </Section>

            {note.tags.length > 0 && (
                <Section title="Tags">
                    <div className="flex flex-wrap gap-1">
                        {note.tags.map((tag) => (
                            <span
                                key={tag}
                                className="inline-flex items-center gap-0.5 rounded-full bg-muted px-2 py-0.5 text-xs"
                            >
                                <Hash className="size-3" />
                                {tag}
                            </span>
                        ))}
                    </div>
                </Section>
            )}

            {properties.length > 0 && (
                <Section title="Properties">
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                        {properties.map(([key, value]) => (
                            <div key={key} className="contents">
                                <dt className="text-muted-foreground">{key}</dt>
                                <dd className="truncate">
                                    {formatValue(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Section>
            )}

            <p className="text-xs text-muted-foreground tabular-nums">
                {note.word_count.toLocaleString()} words · {note.path}
            </p>
        </div>
    );
}
