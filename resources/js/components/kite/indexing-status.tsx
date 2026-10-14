import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { requestJson } from '@/lib/kite-http';
import { show as status } from '@/routes/vault-status';
import type { IndexingProgress, VaultSummary } from '@/types';

const POLL_INTERVAL_MS = 750;

type Props = {
    vault: VaultSummary;
    indexing: IndexingProgress | null;
};

/**
 * Shows background indexing progress and reloads the workspace when it finishes.
 */
export function IndexingStatus({ vault, indexing }: Props) {
    const [progress, setProgress] = useState(indexing);
    const [source, setSource] = useState(indexing);

    if (indexing !== source) {
        setSource(indexing);
        setProgress(indexing);
    }

    const isIndexing = progress !== null;

    useEffect(() => {
        if (!isIndexing) {
            return;
        }

        const interval = window.setInterval(() => {
            requestJson<{ indexing: IndexingProgress | null }>(status(vault.id))
                .then((response) => {
                    setProgress(response.indexing);

                    if (response.indexing === null) {
                        router.reload();
                    }
                })
                .catch(() => undefined);
        }, POLL_INTERVAL_MS);

        return () => window.clearInterval(interval);
    }, [isIndexing, vault.id]);

    if (progress === null) {
        return null;
    }

    const percent =
        progress.total > 0
            ? Math.round((progress.done / progress.total) * 100)
            : 0;

    return (
        <div
            className="space-y-1.5 border-t px-3 py-2.5 text-xs"
            role="status"
            aria-live="polite"
            data-test="indexing-status"
        >
            <div className="flex justify-between text-muted-foreground">
                <span>Indexing notes…</span>
                <span className="tabular-nums">
                    {progress.total > 0
                        ? `${progress.done.toLocaleString()} / ${progress.total.toLocaleString()}`
                        : 'Scanning'}
                </span>
            </div>
            <div
                className="h-1 overflow-hidden rounded-full bg-sidebar-accent"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                aria-label="Indexing progress"
            >
                <div
                    className="h-full rounded-full bg-primary transition-[width] duration-300"
                    style={{ width: `${progress.total > 0 ? percent : 15}%` }}
                />
            </div>
        </div>
    );
}
