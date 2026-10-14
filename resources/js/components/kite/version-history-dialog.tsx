import { History, RotateCcw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { requestJson } from '@/lib/kite-http';
import { diffLines, diffStats, toHunks } from '@/lib/line-diff';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/note-versions';
import type { VaultSummary } from '@/types';

type VersionSummary = {
    id: number;
    source: 'edit' | 'external' | 'restore';
    size: number;
    saved_at: string;
};

type VersionDetail = VersionSummary & {
    contents: string;
    previous: string | null;
};

type Props = {
    vault: VaultSummary;
    noteId: number;
    /** The note's current text, to compare a version against. */
    current: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onRestore: (versionId: number) => void;
};

const SOURCES: Record<VersionSummary['source'], string> = {
    edit: 'Edited in Kite',
    external: 'Changed outside Kite',
    restore: 'Restored',
};

/**
 * Browse a note's saved versions, see what each one changed (or how it differs from now), and
 * restore one.
 */
export function VersionHistoryDialog({
    vault,
    noteId,
    current,
    open,
    onOpenChange,
    onRestore,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag flex h-[85vh] flex-col gap-0 p-0 sm:max-w-5xl"
                data-test="version-history"
            >
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>Version history</DialogTitle>
                    <DialogDescription>
                        Kite keeps a version every few minutes while you edit,
                        and whenever the file changes outside Kite.
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <VersionBrowser
                        vault={vault}
                        noteId={noteId}
                        current={current}
                        onRestore={onRestore}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function VersionBrowser({
    vault,
    noteId,
    current,
    onRestore,
}: Omit<Props, 'open' | 'onOpenChange'>) {
    const [versions, setVersions] = useState<VersionSummary[] | null>(null);
    const [selected, setSelected] = useState<number | null>(null);

    useEffect(() => {
        void requestJson<{ versions: VersionSummary[] }>(
            index({ vault: vault.id, note: noteId }),
        ).then((response) => {
            setVersions(response.versions);
            setSelected(response.versions[0]?.id ?? null);
        });
    }, [vault.id, noteId]);

    if (versions === null) {
        return (
            <div className="space-y-2 p-6" aria-busy="true">
                <Skeleton className="h-8 w-64" />
                <Skeleton className="h-8 w-56" />
                <Skeleton className="h-8 w-60" />
            </div>
        );
    }

    if (versions.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center text-sm text-muted-foreground">
                <History className="size-8" />
                <p>
                    No versions yet. One is kept as soon as you edit this note.
                </p>
            </div>
        );
    }

    return (
        <div className="flex min-h-0 flex-1">
            <ul
                className="w-64 shrink-0 space-y-0.5 overflow-y-auto border-r p-2"
                data-test="version-list"
            >
                {versions.map((version, position) => (
                    <li key={version.id}>
                        <button
                            type="button"
                            onClick={() => setSelected(version.id)}
                            aria-current={version.id === selected}
                            data-test="version"
                            className={cn(
                                'w-full rounded-md px-3 py-2 text-left text-sm hover:bg-accent',
                                version.id === selected && 'bg-accent',
                            )}
                        >
                            <span className="block font-medium">
                                {new Date(version.saved_at).toLocaleString(
                                    undefined,
                                    {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    },
                                )}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                {position === 0 ? 'Latest · ' : ''}
                                {SOURCES[version.source]}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            {selected !== null && (
                <VersionView
                    key={selected}
                    vault={vault}
                    noteId={noteId}
                    versionId={selected}
                    current={current}
                    onRestore={onRestore}
                />
            )}
        </div>
    );
}

function VersionView({
    vault,
    noteId,
    versionId,
    current,
    onRestore,
}: {
    vault: VaultSummary;
    noteId: number;
    versionId: number;
    current: string;
    onRestore: (versionId: number) => void;
}) {
    const [version, setVersion] = useState<VersionDetail | null>(null);
    const [mode, setMode] = useState<'changes' | 'current'>('changes');
    const [restoring, setRestoring] = useState(false);

    useEffect(() => {
        void requestJson<VersionDetail>(
            show({ vault: vault.id, note: noteId, version: versionId }),
        ).then(setVersion);
    }, [vault.id, noteId, versionId]);

    const lines = useMemo(() => {
        if (!version) {
            return [];
        }

        if (mode === 'current') {
            return diffLines(version.contents, current);
        }

        // The oldest version has nothing before it: show its text unchanged.
        return diffLines(
            version.previous ?? version.contents,
            version.contents,
        );
    }, [version, mode, current]);

    if (!version) {
        return (
            <div className="flex flex-1 items-center justify-center">
                <Spinner />
            </div>
        );
    }

    const stats = diffStats(lines);
    const isCurrent = version.contents === current;

    return (
        <div className="flex min-w-0 flex-1 flex-col">
            <div className="flex flex-wrap items-center gap-3 border-b px-4 py-2">
                <ToggleGroup
                    type="single"
                    size="sm"
                    variant="outline"
                    value={mode}
                    onValueChange={(value) =>
                        value && setMode(value as typeof mode)
                    }
                >
                    <ToggleGroupItem value="changes" data-test="mode-changes">
                        Changes in this version
                    </ToggleGroupItem>
                    <ToggleGroupItem value="current" data-test="mode-current">
                        Compare with now
                    </ToggleGroupItem>
                </ToggleGroup>
                <span
                    className="text-xs text-muted-foreground tabular-nums"
                    data-test="diff-stats"
                >
                    <span className="text-emerald-600 dark:text-emerald-400">
                        +{stats.added}
                    </span>{' '}
                    <span className="text-red-600 dark:text-red-400">
                        −{stats.removed}
                    </span>
                </span>
                <Button
                    size="sm"
                    className="ml-auto"
                    disabled={isCurrent || restoring}
                    onClick={() => {
                        setRestoring(true);
                        onRestore(version.id);
                    }}
                    data-test="restore-version"
                    title={
                        isCurrent
                            ? 'The note already has this text'
                            : 'Replace the note with this version. The current text stays in history.'
                    }
                >
                    {restoring ? <Spinner /> : <RotateCcw />}
                    Restore this version
                </Button>
            </div>
            <DiffView
                lines={lines}
                empty={
                    mode === 'changes'
                        ? version.previous === null
                            ? 'This is the oldest version Kite has.'
                            : 'No changes.'
                        : 'Identical to the note now.'
                }
            />
        </div>
    );
}

function DiffView({
    lines,
    empty,
}: {
    lines: ReturnType<typeof diffLines>;
    empty: string;
}) {
    const hunks = toHunks(lines);
    const changed = lines.some((line) => line.type !== 'same');

    if (!changed) {
        return (
            <div className="flex-1 overflow-y-auto p-4">
                <p className="mb-3 text-sm text-muted-foreground">{empty}</p>
                <pre className="font-mono text-xs whitespace-pre-wrap text-muted-foreground">
                    {lines.map((line) => line.text).join('\n')}
                </pre>
            </div>
        );
    }

    return (
        <div
            className="flex-1 overflow-y-auto py-2 font-mono text-xs"
            data-test="diff"
        >
            {hunks.map((hunk, index) =>
                hunk.type === 'skipped' ? (
                    <div
                        key={index}
                        className="my-1 bg-muted/60 px-4 py-1 text-muted-foreground"
                    >
                        ⋯ {hunk.count} unchanged{' '}
                        {hunk.count === 1 ? 'line' : 'lines'}
                    </div>
                ) : (
                    hunk.lines.map((line, lineIndex) => (
                        <div
                            key={`${index}:${lineIndex}`}
                            data-diff={line.type}
                            className={cn(
                                'px-4 break-words whitespace-pre-wrap',
                                line.type === 'added' &&
                                    'bg-emerald-500/15 text-emerald-900 dark:text-emerald-200',
                                line.type === 'removed' &&
                                    'bg-red-500/15 text-red-900 line-through decoration-red-500/40 dark:text-red-200',
                            )}
                        >
                            <span className="mr-3 inline-block w-3 text-muted-foreground select-none">
                                {line.type === 'added'
                                    ? '+'
                                    : line.type === 'removed'
                                      ? '−'
                                      : ' '}
                            </span>
                            {line.text === '' ? ' ' : line.text}
                        </div>
                    ))
                ),
            )}
        </div>
    );
}
