import { useEffect, useRef } from 'react';
import { requestJson } from '@/lib/kite-http';
import { store as sync } from '@/routes/vault-sync';
import type { SyncSummary, VaultSummary } from '@/types';

const SYNC_INTERVAL_MS = 4000;

/**
 * Keep the index in step with files edited outside Kite, while the window is visible.
 */
export function useVaultSync(
    vault: VaultSummary,
    onChanged: (summary: SyncSummary) => void,
): void {
    const onChangedRef = useRef(onChanged);

    useEffect(() => {
        onChangedRef.current = onChanged;
    });

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

        const interval = window.setInterval(run, SYNC_INTERVAL_MS);

        window.addEventListener('focus', run);
        document.addEventListener('visibilitychange', run);

        return () => {
            window.clearInterval(interval);
            window.removeEventListener('focus', run);
            document.removeEventListener('visibilitychange', run);
        };
    }, [vault.id]);
}
