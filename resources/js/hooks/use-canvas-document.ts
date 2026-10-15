import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { SaveState } from '@/hooks/use-note-document';
import type { CanvasData } from '@/lib/canvas';
import { requestJson } from '@/lib/kite-http';
import { update } from '@/routes/canvases';
import type { VaultSummary } from '@/types';

const AUTOSAVE_MS = 600;
const HISTORY_LIMIT = 100;

export type CanvasSaveState = SaveState | 'conflict';

/**
 * The open canvas: its cards and connections, undo and redo, and autosave.
 *
 * Every change is saved shortly after it's made. Saves carry the hash of the file as last loaded
 * or saved, so if the canvas changed on disk meanwhile the save is refused rather than
 * overwriting it, and the canvas offers to reload. A drag or resize is one step in history:
 * start it with `begin()`, move with `preview()`, and finish with `end()`.
 */
export function useCanvasDocument(
    vault: VaultSummary,
    path: string,
    initial: CanvasData,
    initialHash: string | null,
) {
    const [data, setData] = useState<CanvasData>(initial);
    const [saveState, setSaveState] = useState<CanvasSaveState>('saved');
    const latest = useRef(data);
    const hash = useRef(initialHash);
    const past = useRef<CanvasData[]>([]);
    const future = useRef<CanvasData[]>([]);
    const gesture = useRef<CanvasData | null>(null);
    const timer = useRef<number | undefined>(undefined);
    const saving = useRef<Promise<void> | null>(null);
    const dirty = useRef(false);
    const [historySize, setHistorySize] = useState({ past: 0, future: 0 });

    const syncHistory = () =>
        setHistorySize({
            past: past.current.length,
            future: future.current.length,
        });

    const save = useCallback(async (): Promise<void> => {
        window.clearTimeout(timer.current);

        if (saving.current) {
            await saving.current;
        }

        if (!dirty.current || hash.current === null) {
            return;
        }

        dirty.current = false;
        setSaveState('saving');

        const sent = latest.current;

        saving.current = requestJson<{ hash: string }>(
            update({ vault: vault.id, path }),
            { base_hash: hash.current, nodes: sent.nodes, edges: sent.edges },
        )
            .then((response) => {
                hash.current = response.hash;
                setSaveState(dirty.current ? 'unsaved' : 'saved');
            })
            .catch((error: unknown) => {
                const status = (error as { response?: { status?: number } })
                    .response?.status;

                if (status === 409) {
                    // Stop saving: the file on disk is newer than this window's copy.
                    hash.current = null;
                    setSaveState('conflict');
                    toast.error('This canvas changed on disk.', {
                        duration: Infinity,
                        action: {
                            label: 'Reload',
                            onClick: () => router.reload(),
                        },
                    });

                    return;
                }

                dirty.current = true;
                setSaveState('error');
                toast.error('Could not save the canvas.');
            })
            .finally(() => {
                saving.current = null;
            });

        await saving.current;
    }, [vault.id, path]);

    const commit = (next: CanvasData) => {
        latest.current = next;
        setData(next);

        if (hash.current === null) {
            return;
        }

        dirty.current = true;
        setSaveState('unsaved');
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => void save(), AUTOSAVE_MS);
    };

    const remember = (snapshot: CanvasData) => {
        past.current = [...past.current, snapshot].slice(-HISTORY_LIMIT);
        future.current = [];
        syncHistory();
    };

    /** Make a change as one step in history. */
    const change = (updater: (current: CanvasData) => CanvasData) => {
        const next = updater(latest.current);

        if (next !== latest.current) {
            remember(latest.current);
            commit(next);
        }
    };

    const begin = () => {
        gesture.current ??= latest.current;
    };

    /** Change during a gesture, without a step in history until it ends. */
    const preview = (updater: (current: CanvasData) => CanvasData) => {
        latest.current = updater(latest.current);
        setData(latest.current);
    };

    const end = () => {
        const before = gesture.current;

        gesture.current = null;

        if (before && before !== latest.current) {
            remember(before);
            commit(latest.current);
        }
    };

    const undo = () => {
        const previous = past.current.at(-1);

        if (previous) {
            past.current = past.current.slice(0, -1);
            future.current = [latest.current, ...future.current];
            syncHistory();
            commit(previous);
        }
    };

    const redo = () => {
        const next = future.current[0];

        if (next) {
            future.current = future.current.slice(1);
            past.current = [...past.current, latest.current];
            syncHistory();
            commit(next);
        }
    };

    // Save what's pending when leaving the canvas or the window.
    useEffect(() => {
        const flush = () => void save();

        window.addEventListener('beforeunload', flush);
        document.addEventListener('visibilitychange', flush);

        return () => {
            window.removeEventListener('beforeunload', flush);
            document.removeEventListener('visibilitychange', flush);
            flush();
        };
    }, [save]);

    return {
        data,
        saveState,
        readOnly: saveState === 'conflict' || initialHash === null,
        change,
        begin,
        preview,
        end,
        undo,
        redo,
        canUndo: historySize.past > 0,
        canRedo: historySize.future > 0,
        save,
    };
}
