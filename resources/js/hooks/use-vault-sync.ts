import { useEffect, useRef, useState } from 'react';
import { requestJson } from '@/lib/kite-http';
import { onNativeEvent } from '@/lib/native-events';
import { store as sync } from '@/routes/vault-sync';
import type { SyncSummary, VaultSummary } from '@/types';

const POLL_INTERVAL_MS = 4000;
const WATCHED_POLL_INTERVAL_MS = 30000;

type VaultEvent = { vaultId: number };
type WatchStatusEvent = VaultEvent & { watching: boolean };

/**
 * Keep the workspace in step with files edited outside Kite.
 *
 * In the desktop app a native file watcher pushes changes instantly and polling drops to a
 * slow safety net; if the watcher is unavailable (or in a browser) it polls every few seconds.
 */
export function useVaultSync(
    vault: VaultSummary,
    onChanged: (summary?: SyncSummary) => void,
    initiallyWatching = false,
): boolean {
    const onChangedRef = useRef(onChanged);
    const [watching, setWatching] = useState(initiallyWatching);

    useEffect(() => {
        onChangedRef.current = onChanged;
    });

    useEffect(() => {
        const stopChanged = onNativeEvent<VaultEvent>(
            'App\\Events\\VaultChanged',
            (payload) => {
                if (payload.vaultId === vault.id) {
                    onChangedRef.current();
                }
            },
        );

        const stopStatus = onNativeEvent<WatchStatusEvent>(
            'App\\Events\\VaultWatchStatusChanged',
            (payload) => {
                if (payload.vaultId === vault.id) {
                    setWatching(payload.watching);
                }
            },
        );

        return () => {
            stopChanged();
            stopStatus();
        };
    }, [vault.id]);

    useEffect(() => {
        let inFlight = false;

        const run = () => {
            if (inFlight || document.visibilityState !== 'visible') {
                return;
            }

            inFlight = true;

            requestJson<SyncSummary>(sync(vault.id))
                .then((summary) => {
                    if (summary.changed) {
                        onChangedRef.current(summary);
                    }
                })
                .catch(() => undefined)
                .finally(() => {
                    inFlight = false;
                });
        };

        const interval = window.setInterval(
            run,
            watching ? WATCHED_POLL_INTERVAL_MS : POLL_INTERVAL_MS,
        );

        window.addEventListener('focus', run);
        document.addEventListener('visibilitychange', run);

        return () => {
            window.clearInterval(interval);
            window.removeEventListener('focus', run);
            document.removeEventListener('visibilitychange', run);
        };
    }, [vault.id, watching]);

    return watching;
}
