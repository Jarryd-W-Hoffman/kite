import { http } from '@inertiajs/react';

/** How long an unlocked vault stays unlocked without any typing or clicking. */
export const IDLE_LOCK_MS = 15 * 60 * 1000;

const HEADER = 'X-Kite-Vault-Key';

type Unlocked = { key: string; timer: number };

const unlocked = new Map<number, Unlocked>();
const listeners = new Set<() => void>();
const beforeLock = new Set<(vaultId: number) => Promise<unknown> | void>();

const notify = () => listeners.forEach((listener) => listener());

const schedule = (vaultId: number) =>
    window.setTimeout(() => void lockVault(vaultId), IDLE_LOCK_MS);

/**
 * The keys of unlocked vaults. They only ever live in this window's memory: reloading the app,
 * locking the vault or leaving it idle forgets them, and locked notes close again.
 */
export function vaultKey(vaultId: number): string | null {
    return unlocked.get(vaultId)?.key ?? null;
}

export function isUnlocked(vaultId: number): boolean {
    return unlocked.has(vaultId);
}

export function rememberVaultKey(vaultId: number, key: string): void {
    window.clearTimeout(unlocked.get(vaultId)?.timer);
    unlocked.set(vaultId, { key, timer: schedule(vaultId) });
    notify();
}

/** Forget a vault's key, after giving open locked notes a chance to save. */
export async function lockVault(vaultId: number): Promise<void> {
    if (!unlocked.has(vaultId)) {
        return;
    }

    await Promise.allSettled(
        [...beforeLock].map((save) => Promise.resolve(save(vaultId))),
    );

    window.clearTimeout(unlocked.get(vaultId)?.timer);
    unlocked.delete(vaultId);
    notify();
}

export function subscribeToVaultKeys(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Run something (such as saving a locked note) just before a vault locks. */
export function onBeforeVaultLock(
    handler: (vaultId: number) => Promise<unknown> | void,
): () => void {
    beforeLock.add(handler);

    return () => beforeLock.delete(handler);
}

/**
 * Send the key with the app's own requests to an unlocked vault, so the server can open and save
 * its locked notes, and keep the vault unlocked while it's being used.
 */
export function installVaultKeys(): void {
    http.onRequest((config) => {
        const vaultId = Number(
            /\/vaults\/(\d+)(?:\/|$)/.exec(String(config.url ?? ''))?.[1],
        );
        const key = vaultKey(vaultId);

        return key === null
            ? config
            : { ...config, headers: { ...config.headers, [HEADER]: key } };
    });

    const extend = () => {
        for (const [vaultId, entry] of unlocked) {
            window.clearTimeout(entry.timer);
            entry.timer = schedule(vaultId);
        }
    };

    window.addEventListener('keydown', extend, { capture: true });
    window.addEventListener('pointerdown', extend, { capture: true });
}
