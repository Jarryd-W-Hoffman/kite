import { LockKeyhole, TriangleAlert } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/kite-http';
import {
    isUnlocked,
    rememberVaultKey,
    subscribeToVaultKeys,
} from '@/lib/vault-keys';
import { store as storeVaultKey } from '@/routes/vault-key';
import { store as storeVaultPassphrase } from '@/routes/vault-passphrase';
import type { VaultSummary } from '@/types';

/** Whether the vault's locked notes can be opened right now. */
export function useVaultUnlocked(vaultId: number): boolean {
    return useSyncExternalStore(subscribeToVaultKeys, () =>
        isUnlocked(vaultId),
    );
}

function errorMessage(error: unknown, fallback: string): string {
    const body = (error as { response?: { data?: string } }).response?.data;

    try {
        const parsed = JSON.parse(body ?? '') as {
            errors?: Record<string, string[]>;
        };

        return Object.values(parsed.errors ?? {})[0]?.[0] ?? fallback;
    } catch {
        return fallback;
    }
}

/**
 * The vault passphrase field and button; the key it unlocks is kept in memory only.
 */
export function UnlockForm({
    vault,
    onUnlocked,
    autoFocus = true,
}: {
    vault: VaultSummary;
    onUnlocked: () => void;
    autoFocus?: boolean;
}) {
    const [passphrase, setPassphrase] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    return (
        <form
            className="grid gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                setProcessing(true);
                setError(null);
                requestJson<{ key: string }>(storeVaultKey(vault.id), {
                    passphrase,
                })
                    .then(({ key }) => {
                        rememberVaultKey(vault.id, key);
                        onUnlocked();
                    })
                    .catch((caught: unknown) =>
                        setError(
                            errorMessage(caught, 'Could not unlock the vault.'),
                        ),
                    )
                    .finally(() => setProcessing(false));
            }}
            data-test="unlock-form"
        >
            <Label htmlFor="vault-passphrase" className="sr-only">
                Vault passphrase
            </Label>
            <div className="flex gap-2">
                <Input
                    id="vault-passphrase"
                    name="passphrase"
                    type="password"
                    autoComplete="current-password"
                    autoFocus={autoFocus}
                    placeholder="Vault passphrase"
                    value={passphrase}
                    onChange={(event) => setPassphrase(event.target.value)}
                />
                <Button
                    type="submit"
                    disabled={processing || passphrase === ''}
                >
                    {processing && <Spinner />}
                    Unlock
                </Button>
            </div>
            {error && (
                <p className="text-sm text-destructive" role="alert">
                    {error}
                </p>
            )}
        </form>
    );
}

/**
 * Shown in place of a locked note until the vault is unlocked.
 */
export function LockedNoteScreen({
    vault,
    title,
    onUnlocked,
}: {
    vault: VaultSummary;
    title: string;
    onUnlocked: () => void;
}) {
    return (
        <div
            className="flex flex-1 items-center justify-center p-8"
            data-test="locked-note"
        >
            <div className="grid w-full max-w-sm gap-5 text-center">
                <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-kite-accent-soft text-primary">
                    <LockKeyhole className="size-6" />
                </span>
                <div className="grid gap-1.5">
                    <h2 className="text-lg font-semibold">
                        “{title}” is locked
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Enter the vault’s passphrase to read and edit it. Locked
                        notes stay open while you work, and lock again after 15
                        minutes away.
                    </p>
                </div>
                <div className="text-left">
                    <UnlockForm vault={vault} onUnlocked={onUnlocked} />
                </div>
            </div>
        </div>
    );
}

/**
 * Unlock the vault before doing something that needs its key, such as locking a note.
 */
export function UnlockDialog({
    vault,
    open,
    onOpenChange,
    onUnlocked,
}: {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onUnlocked: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="unlock-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Unlock {vault.name}</DialogTitle>
                    <DialogDescription>
                        Enter the vault’s passphrase to work with its locked
                        notes.
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <UnlockForm
                        vault={vault}
                        onUnlocked={() => {
                            onOpenChange(false);
                            onUnlocked();
                        }}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * Choose the passphrase that locks notes in this vault. There's no way to recover it, so the
 * dialog says so plainly and asks for it twice.
 */
export function SetPassphraseDialog({
    vault,
    open,
    onOpenChange,
    onDone,
}: {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDone: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="passphrase-dialog"
            >
                {open && (
                    <PassphraseForm
                        vault={vault}
                        onDone={() => {
                            onOpenChange(false);
                            onDone();
                        }}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function PassphraseForm({
    vault,
    onDone,
}: {
    vault: VaultSummary;
    onDone: () => void;
}) {
    const [passphrase, setPassphrase] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const mismatch = confirmation !== '' && confirmation !== passphrase;

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                setProcessing(true);
                setError(null);
                requestJson<{ key: string }>(storeVaultPassphrase(vault.id), {
                    passphrase,
                    passphrase_confirmation: confirmation,
                })
                    .then(({ key }) => {
                        rememberVaultKey(vault.id, key);
                        onDone();
                    })
                    .catch((caught: unknown) =>
                        setError(
                            errorMessage(
                                caught,
                                'Could not set the passphrase.',
                            ),
                        ),
                    )
                    .finally(() => setProcessing(false));
            }}
        >
            <DialogHeader>
                <DialogTitle>Choose a passphrase for locked notes</DialogTitle>
                <DialogDescription>
                    Every locked note in {vault.name} uses this passphrase.
                </DialogDescription>
            </DialogHeader>
            <div className="flex gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm">
                <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <p>
                    There’s no way to recover a forgotten passphrase. Without
                    it, locked notes can’t be read by anyone, including you.
                </p>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="new-passphrase">Passphrase</Label>
                <Input
                    id="new-passphrase"
                    name="passphrase"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
                    value={passphrase}
                    onChange={(event) => setPassphrase(event.target.value)}
                />
                <p className="text-xs text-muted-foreground">
                    At least 8 characters. A few unrelated words make a strong,
                    memorable one.
                </p>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="confirm-passphrase">Type it again</Label>
                <Input
                    id="confirm-passphrase"
                    name="passphrase_confirmation"
                    type="password"
                    autoComplete="new-password"
                    value={confirmation}
                    onChange={(event) => setConfirmation(event.target.value)}
                />
                {mismatch && (
                    <p className="text-sm text-destructive">
                        The passphrases don’t match.
                    </p>
                )}
            </div>
            {error && (
                <p className="text-sm text-destructive" role="alert">
                    {error}
                </p>
            )}
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={
                        processing ||
                        passphrase.length < 8 ||
                        confirmation !== passphrase
                    }
                >
                    {processing && <Spinner />}
                    Set passphrase and lock
                </Button>
            </DialogFooter>
        </form>
    );
}
