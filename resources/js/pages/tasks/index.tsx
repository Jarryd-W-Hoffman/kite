import { Head, Link, router } from '@inertiajs/react';
import { CheckSquare, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { cn } from '@/lib/utils';
import { update as updateTask } from '@/routes/note-tasks';
import { show } from '@/routes/notes';
import type { WorkspaceProps } from '@/types';

type Task = {
    id: number;
    note_id: number;
    title: string;
    path: string;
    line: number;
    text: string;
    done: boolean;
    due: string | null;
    tags: string[];
};

type Props = WorkspaceProps & { tasks: Task[] };

type Status = 'open' | 'done' | 'all';

const DUE_MARKER = /\s*(?:📅|\bdue::?)\s*\d{4}-\d{2}-\d{2}\b/u;

function localDate(offset = 0): string {
    const date = new Date();
    date.setDate(date.getDate() + offset);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Which heading an open task falls under. */
function bucket(task: Task): string {
    if (task.done) {
        return 'Done';
    }

    if (task.due === null) {
        return 'No date';
    }

    const today = localDate();

    if (task.due < today) {
        return 'Overdue';
    }

    return task.due === today ? 'Today' : 'Upcoming';
}

const ORDER = ['Overdue', 'Today', 'Upcoming', 'No date', 'Done'];

export default function TasksIndex({
    vault,
    vaults,
    tree,
    folders,
    colour_groups: colourGroups,
    tags,
    indexing,
    watching,
    tasks,
}: Props) {
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [status, setStatus] = useState<Status>('open');
    const [query, setQuery] = useState('');
    const [tag, setTag] = useState('');
    const [pending, setPending] = useState<Record<number, boolean>>({});

    useVaultSync(
        vault,
        () =>
            router.reload({
                only: ['tasks', 'open_tasks', 'tree', 'folders', 'tags'],
            }),
        watching,
    );

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return tasks.filter(
            (task) =>
                (status === 'all' || task.done === (status === 'done')) &&
                (tag === '' || task.tags.includes(tag)) &&
                (needle === '' ||
                    task.text.toLowerCase().includes(needle) ||
                    task.title.toLowerCase().includes(needle)),
        );
    }, [tasks, status, query, tag]);

    const groups = ORDER.map((name) => ({
        name,
        tasks: visible.filter((task) => bucket(task) === name),
    })).filter((group) => group.tasks.length > 0);

    const toggle = (task: Task) => {
        setPending((current) => ({ ...current, [task.id]: !task.done }));
        router.put(
            updateTask({ vault: vault.id, note: task.note_id, line: task.line })
                .url,
            { text: task.text, done: !task.done },
            {
                only: ['tasks', 'open_tasks'],
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    toast.error(errors.task ?? 'Could not update the task.'),
                onFinish: () =>
                    setPending((current) => {
                        const next = { ...current };
                        delete next[task.id];

                        return next;
                    }),
            },
        );
    };

    const openCount = tasks.filter((task) => !task.done).length;

    return (
        <>
            <Head title={`Tasks · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={colourGroups}
                    tags={tags}
                    activeNoteId={null}
                    tasksActive
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-3 border-b border-border/70 px-4">
                        <h1 className="flex items-center gap-2 text-[13.5px] font-semibold">
                            <CheckSquare className="size-4 text-primary" />
                            Tasks
                        </h1>
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground tabular-nums">
                            {openCount} open
                        </span>
                        <div className="kite-no-drag ml-auto flex items-center gap-2">
                            <div
                                className="flex rounded-lg bg-muted p-0.5"
                                role="radiogroup"
                                aria-label="Status"
                            >
                                {(['open', 'done', 'all'] as const).map(
                                    (value) => (
                                        <button
                                            key={value}
                                            type="button"
                                            role="radio"
                                            aria-checked={status === value}
                                            onClick={() => setStatus(value)}
                                            data-test={`tasks-${value}`}
                                            className={cn(
                                                'h-7 rounded-md px-2.5 text-[12.5px] text-muted-foreground capitalize transition-colors',
                                                status === value &&
                                                    'bg-background text-foreground shadow-kite-sm',
                                            )}
                                        >
                                            {value}
                                        </button>
                                    ),
                                )}
                            </div>
                            <select
                                value={tag}
                                onChange={(event) => setTag(event.target.value)}
                                aria-label="Filter by tag"
                                className="kite-select max-w-40"
                            >
                                <option value="">All tags</option>
                                {tags.map((item) => (
                                    <option key={item.name} value={item.name}>
                                        #{item.name}
                                    </option>
                                ))}
                            </select>
                            <label className="flex h-8 items-center gap-2 rounded-md border bg-background px-2.5 shadow-kite-sm focus-within:ring-2 focus-within:ring-ring">
                                <Search className="size-3.5 text-muted-foreground" />
                                <input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Filter tasks…"
                                    aria-label="Filter tasks"
                                    className="w-40 bg-transparent text-sm outline-none"
                                />
                            </label>
                        </div>
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {groups.length === 0 ? (
                            <EmptyState
                                icon={CheckSquare}
                                title={
                                    tasks.length === 0
                                        ? 'No tasks yet'
                                        : 'Nothing matches'
                                }
                                description={
                                    tasks.length === 0
                                        ? 'Write “- [ ] something” in any note, optionally with due:2026-10-14, and it shows up here.'
                                        : 'Try another status, tag or filter.'
                                }
                                className="h-full"
                            />
                        ) : (
                            <div className="mx-auto max-w-3xl space-y-8 px-8 pt-10 pb-24">
                                {groups.map((group) => (
                                    <section
                                        key={group.name}
                                        data-test={`task-group-${group.name.toLowerCase().replace(' ', '-')}`}
                                    >
                                        <h2
                                            className={cn(
                                                'mb-2 flex items-center gap-2 px-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase',
                                                group.name === 'Overdue' &&
                                                    'text-destructive-foreground',
                                            )}
                                        >
                                            {group.name}
                                            <span className="rounded-full bg-muted px-1.5 text-[10px] font-medium tabular-nums">
                                                {group.tasks.length}
                                            </span>
                                        </h2>
                                        <ul className="overflow-hidden rounded-xl border bg-card shadow-kite-sm">
                                            {group.tasks.map((task) => (
                                                <TaskRow
                                                    key={task.id}
                                                    vaultId={vault.id}
                                                    task={task}
                                                    done={
                                                        pending[task.id] ??
                                                        task.done
                                                    }
                                                    onToggle={() =>
                                                        toggle(task)
                                                    }
                                                />
                                            ))}
                                        </ul>
                                    </section>
                                ))}
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

function TaskRow({
    vaultId,
    task,
    done,
    onToggle,
}: {
    vaultId: number;
    task: Task;
    done: boolean;
    onToggle: () => void;
}) {
    const overdue = !done && task.due !== null && task.due < localDate();

    return (
        <li
            className="group flex items-start gap-3 border-b px-4 py-2.5 last:border-b-0 hover:bg-accent/40"
            data-test="task"
        >
            <input
                type="checkbox"
                checked={done}
                onChange={onToggle}
                aria-label={task.text}
                className="kite-task-checkbox mt-1"
                data-test="task-checkbox"
            />
            <div className="min-w-0 flex-1">
                <p
                    className={cn(
                        'text-[14px] leading-relaxed',
                        done && 'text-muted-foreground line-through',
                    )}
                >
                    {task.text.replace(DUE_MARKER, '')}
                </p>
                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    <Link
                        href={`${show({ vault: vaultId, note: task.note_id }).url}#L${task.line}`}
                        className="truncate hover:text-foreground hover:underline"
                    >
                        {task.title}
                    </Link>
                    {task.due && (
                        <span
                            className={cn(
                                'rounded-md bg-muted px-1.5 py-px tabular-nums',
                                overdue &&
                                    'bg-destructive/10 text-destructive-foreground',
                            )}
                        >
                            {new Date(`${task.due}T00:00`).toLocaleDateString(
                                undefined,
                                { month: 'short', day: 'numeric' },
                            )}
                        </span>
                    )}
                    {task.tags.map((name) => (
                        <span key={name} className="text-primary">
                            #{name}
                        </span>
                    ))}
                </div>
            </div>
        </li>
    );
}
