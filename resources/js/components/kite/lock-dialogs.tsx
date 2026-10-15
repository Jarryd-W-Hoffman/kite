import { router } from '@inertiajs/react';
import {
    Check,
    Copy,
    KeyRound,
    LockKeyhole,
    TriangleAlert,
} from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useClipboard } from '@/hooks/use-clipboard';
import type { LockStep } from '@/hooks/use-note-lock';
import { requestJson } from '@/lib/kite-http';
import {
    isUnlocked,
    rememberVaultKey,
    subscribeToVaultKeys,
} from '@/lib/vault-keys';
import { store as storeVaultKey } from '@/routes/vault-key';
import {
    store as storeVaultPassphrase,
    update as updateVaultPassphrase,
} from '@/routes/vault-passphrase';
import { store as storeRecoveryKey } from '@/routes/vault-recovery-key';
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
    onForgot,
    autoFocus = true,
}: {
    vault: VaultSummary;
    onUnlocked: () => void;
    onForgot?: () => void;
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
            {onForgot && (
                <button
                    type="button"
                    className="justify-self-start text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    onClick={onForgot}
                    data-test="forgot-passphrase"
                >
                    Forgot the passphrase?
                </button>
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
    const [recovering, setRecovering] = useState(false);

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
                    {recovering ? (
                        <RecoverForm
                            vault={vault}
                            onDone={onUnlocked}
                            onCancel={() => setRecovering(false)}
                        />
                    ) : (
                        <UnlockForm
                            vault={vault}
                            onUnlocked={onUnlocked}
                            onForgot={() => setRecovering(true)}
                        />
                    )}
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
    const [recovering, setRecovering] = useState(false);
    const done = () => {
        onOpenChange(false);
        onUnlocked();
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setRecovering(false);
                onOpenChange(next);
            }}
        >
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="unlock-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Unlock {vault.name}</DialogTitle>
                    <DialogDescription>
                        {recovering
                            ? 'Enter your recovery key and choose a new passphrase.'
                            : 'Enter the vault’s passphrase to work with its locked notes.'}
                    </DialogDescription>
                </DialogHeader>
                {open &&
                    (recovering ? (
                        <RecoverForm
                            vault={vault}
                            onDone={done}
                            onCancel={() => setRecovering(false)}
                        />
                    ) : (
                        <UnlockForm
                            vault={vault}
                            onUnlocked={done}
                            onForgot={() => setRecovering(true)}
                        />
                    ))}
            </DialogContent>
        </Dialog>
    );
}

/**
 * Choose the passphrase that locks notes in this vault, then save the recovery key made with it:
 * the only way back in if the passphrase is forgotten.
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
    const [recoveryKey, setRecoveryKey] = useState<string | null>(null);

    if (recoveryKey !== null) {
        return (
            <RecoveryKeyNotice
                recoveryKey={recoveryKey}
                onDone={onDone}
                doneLabel="Lock note"
            />
        );
    }

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                setProcessing(true);
                setError(null);
                requestJson<{ key: string; recovery_key: string }>(
                    storeVaultPassphrase(vault.id),
                    {
                        passphrase,
                        passphrase_confirmation: confirmation,
                    },
                )
                    .then(({ key, recovery_key }) => {
                        rememberVaultKey(vault.id, key);
                        setRecoveryKey(recovery_key);
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
                    Next, you’ll get a recovery key in case you forget it.
                </DialogDescription>
            </DialogHeader>
            <NewPassphraseFields
                passphrase={passphrase}
                confirmation={confirmation}
                onPassphraseChange={setPassphrase}
                onConfirmationChange={setConfirmation}
                autoFocus
            />
            <FormError error={error} />
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={
                        processing || !isNewPassphrase(passphrase, confirmation)
                    }
                >
                    {processing && <Spinner />}
                    Set passphrase
                </Button>
            </DialogFooter>
        </form>
    );
}

const MIN_PASSPHRASE_LENGTH = 8;

function isNewPassphrase(passphrase: string, confirmation: string): boolean {
    return (
        passphrase.length >= MIN_PASSPHRASE_LENGTH &&
        confirmation === passphrase
    );
}

function FormError({ error }: { error: string | null }) {
    return (
        error && (
            <p className="text-sm text-destructive" role="alert">
                {error}
            </p>
        )
    );
}

/**
 * A new passphrase, typed twice.
 */
function NewPassphraseFields({
    passphrase,
    confirmation,
    onPassphraseChange,
    onConfirmationChange,
    autoFocus = false,
}: {
    passphrase: string;
    confirmation: string;
    onPassphraseChange: (value: string) => void;
    onConfirmationChange: (value: string) => void;
    autoFocus?: boolean;
}) {
    return (
        <>
            <div className="grid gap-1.5">
                <Label htmlFor="new-passphrase">New passphrase</Label>
                <Input
                    id="new-passphrase"
                    name="passphrase"
                    type="password"
                    autoComplete="new-password"
                    autoFocus={autoFocus}
                    value={passphrase}
                    onChange={(event) => onPassphraseChange(event.target.value)}
                />
                <p className="text-xs text-muted-foreground">
                    At least {MIN_PASSPHRASE_LENGTH} characters. A few unrelated
                    words make a strong, memorable one.
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
                    onChange={(event) =>
                        onConfirmationChange(event.target.value)
                    }
                />
                {confirmation !== '' && confirmation !== passphrase && (
                    <p className="text-sm text-destructive">
                        The passphrases don’t match.
                    </p>
                )}
            </div>
        </>
    );
}

const RECOVERY_KEY_ADVICE =
    'If you forget the passphrase, this key is the only way to open your locked notes. Keep it somewhere safe outside this vault, such as a password manager or on paper.';

/**
 * A recovery key, shown once. It can't be shown again, so it has to be saved before moving on.
 */
function RecoveryKeyNotice({
    recoveryKey,
    onDone,
    doneLabel = 'Done',
    titled = true,
}: {
    recoveryKey: string;
    onDone: () => void;
    doneLabel?: string;
    /** Whether this is the dialog's title; off where it isn't in a dialog or the dialog has one. */
    titled?: boolean;
}) {
    const [saved, setSaved] = useState(false);
    const [copiedText, copy] = useClipboard();

    return (
        <div className="grid gap-4" data-test="recovery-key-notice">
            {titled ? (
                <DialogHeader>
                    <DialogTitle>Save your recovery key</DialogTitle>
                    <DialogDescription>{RECOVERY_KEY_ADVICE}</DialogDescription>
                </DialogHeader>
            ) : (
                <div className="grid gap-1.5">
                    <h3 className="font-semibold">
                        Save your new recovery key
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {RECOVERY_KEY_ADVICE}
                    </p>
                </div>
            )}
            <div className="flex items-center gap-2 rounded-lg border bg-muted/50 p-3">
                <KeyRound className="size-4 shrink-0 text-muted-foreground" />
                <code
                    className="flex-1 font-mono text-sm break-all select-all"
                    data-test="recovery-key"
                >
                    {recoveryKey}
                </code>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Copy recovery key"
                    onClick={() => void copy(recoveryKey)}
                >
                    {copiedText === recoveryKey ? <Check /> : <Copy />}
                </Button>
            </div>
            <div className="flex gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm">
                <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <p>
                    Kite won’t show this key again. Without it or the
                    passphrase, locked notes can’t be read by anyone, including
                    you.
                </p>
            </div>
            <Label className="flex items-center gap-2 font-normal">
                <Checkbox
                    checked={saved}
                    onCheckedChange={(checked) => setSaved(checked === true)}
                    data-test="recovery-key-saved"
                />
                I’ve saved my recovery key
            </Label>
            <DialogFooter>
                <Button type="button" disabled={!saved} onClick={onDone}>
                    {doneLabel}
                </Button>
            </DialogFooter>
        </div>
    );
}

/**
 * Forgot the passphrase: the recovery key unlocks the vault and sets a new one. The recovery key
 * that was used is replaced, so the new one is shown before carrying on.
 */
function RecoverForm({
    vault,
    onDone,
    onCancel,
}: {
    vault: VaultSummary;
    onDone: () => void;
    onCancel: () => void;
}) {
    const [recoveryKey, setRecoveryKey] = useState('');
    const [passphrase, setPassphrase] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [replacement, setReplacement] = useState<string | null>(null);

    if (replacement !== null) {
        return (
            <RecoveryKeyNotice
                recoveryKey={replacement}
                onDone={onDone}
                doneLabel="Open locked notes"
                titled={false}
            />
        );
    }

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                setProcessing(true);
                setError(null);
                requestJson<{ key: string; recovery_key: string }>(
                    updateVaultPassphrase(vault.id),
                    {
                        recovery_key: recoveryKey,
                        passphrase,
                        passphrase_confirmation: confirmation,
                    },
                )
                    .then(({ key, recovery_key }) => {
                        rememberVaultKey(vault.id, key);
                        setReplacement(recovery_key);
                    })
                    .catch((caught: unknown) =>
                        setError(
                            errorMessage(
                                caught,
                                'Could not reset the passphrase.',
                            ),
                        ),
                    )
                    .finally(() => setProcessing(false));
            }}
            data-test="recover-form"
        >
            <div className="grid gap-1.5">
                <Label htmlFor="recovery-key">Recovery key</Label>
                <Input
                    id="recovery-key"
                    name="recovery_key"
                    autoComplete="off"
                    spellCheck={false}
                    autoFocus
                    className="font-mono"
                    placeholder="XXXX-XXXX-XXXX-XXXX-…"
                    value={recoveryKey}
                    onChange={(event) => setRecoveryKey(event.target.value)}
                />
            </div>
            <NewPassphraseFields
                passphrase={passphrase}
                confirmation={confirmation}
                onPassphraseChange={setPassphrase}
                onConfirmationChange={setConfirmation}
            />
            <FormError error={error} />
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel}>
                    Back
                </Button>
                <Button
                    type="submit"
                    disabled={
                        processing ||
                        recoveryKey.trim() === '' ||
                        !isNewPassphrase(passphrase, confirmation)
                    }
                >
                    {processing && <Spinner />}
                    Reset passphrase
                </Button>
            </div>
        </form>
    );
}

/**
 * Change the vault's passphrase. Locked notes stay as they are: only the vault key's wrapping
 * changes, and the recovery key keeps working.
 */
export function ChangePassphraseDialog({
    vault,
    open,
    onOpenChange,
}: {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="change-passphrase-dialog"
            >
                {open && (
                    <ChangePassphraseForm
                        vault={vault}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ChangePassphraseForm({
    vault,
    onDone,
}: {
    vault: VaultSummary;
    onDone: () => void;
}) {
    const [current, setCurrent] = useState('');
    const [passphrase, setPassphrase] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                setProcessing(true);
                setError(null);
                requestJson<{ key: string }>(updateVaultPassphrase(vault.id), {
                    current_passphrase: current,
                    passphrase,
                    passphrase_confirmation: confirmation,
                })
                    .then(({ key }) => {
                        rememberVaultKey(vault.id, key);
                        toast.success('Changed the vault passphrase.');
                        onDone();
                    })
                    .catch((caught: unknown) =>
                        setError(
                            errorMessage(
                                caught,
                                'Could not change the passphrase.',
                            ),
                        ),
                    )
                    .finally(() => setProcessing(false));
            }}
        >
            <DialogHeader>
                <DialogTitle>Change the vault passphrase</DialogTitle>
                <DialogDescription>
                    Locked notes in {vault.name} stay as they are, and your
                    recovery key keeps working.
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-1.5">
                <Label htmlFor="current-passphrase">Current passphrase</Label>
                <Input
                    id="current-passphrase"
                    name="current_passphrase"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    value={current}
                    onChange={(event) => setCurrent(event.target.value)}
                />
            </div>
            <NewPassphraseFields
                passphrase={passphrase}
                confirmation={confirmation}
                onPassphraseChange={setPassphrase}
                onConfirmationChange={setConfirmation}
            />
            <FormError error={error} />
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
                        current === '' ||
                        !isNewPassphrase(passphrase, confirmation)
                    }
                >
                    {processing && <Spinner />}
                    Change passphrase
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * Make a new recovery key (or the first one, for a vault locked before recovery keys existed).
 * The one before it stops working.
 */
export function RecoveryKeyDialog({
    vault,
    open,
    onOpenChange,
    replacing,
    onCreated,
}: {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    replacing: boolean;
    onCreated: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="recovery-key-dialog"
            >
                {open && (
                    <RecoveryKeyForm
                        vault={vault}
                        replacing={replacing}
                        onCreated={onCreated}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function RecoveryKeyForm({
    vault,
    replacing,
    onCreated,
    onDone,
}: {
    vault: VaultSummary;
    replacing: boolean;
    onCreated: () => void;
    onDone: () => void;
}) {
    const [passphrase, setPassphrase] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [recoveryKey, setRecoveryKey] = useState<string | null>(null);

    if (recoveryKey !== null) {
        return <RecoveryKeyNotice recoveryKey={recoveryKey} onDone={onDone} />;
    }

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                setProcessing(true);
                setError(null);
                requestJson<{ recovery_key: string }>(
                    storeRecoveryKey(vault.id),
                    { passphrase },
                )
                    .then(({ recovery_key }) => {
                        setRecoveryKey(recovery_key);
                        onCreated();
                    })
                    .catch((caught: unknown) =>
                        setError(
                            errorMessage(
                                caught,
                                'Could not make a recovery key.',
                            ),
                        ),
                    )
                    .finally(() => setProcessing(false));
            }}
        >
            <DialogHeader>
                <DialogTitle>
                    {replacing ? 'New recovery key' : 'Create a recovery key'}
                </DialogTitle>
                <DialogDescription>
                    {replacing
                        ? 'Your current recovery key will stop working. Enter the vault’s passphrase to continue.'
                        : 'A recovery key opens your locked notes if you forget the passphrase. Enter the passphrase to continue.'}
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-1.5">
                <Label htmlFor="recovery-passphrase">Passphrase</Label>
                <Input
                    id="recovery-passphrase"
                    name="passphrase"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    value={passphrase}
                    onChange={(event) => setPassphrase(event.target.value)}
                />
            </div>
            <FormError error={error} />
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={processing || passphrase === ''}
                >
                    {processing && <Spinner />}
                    {replacing ? 'Replace recovery key' : 'Create recovery key'}
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * The dialogs of a note's lock flow, one at a time: choosing a passphrase or unlocking the vault
 * before a note is locked, changing the passphrase, or making a recovery key.
 */
export function NoteLockDialogs({
    vault,
    step,
    onClose,
    onReadyToLock,
    recoveryKeySet,
}: {
    vault: VaultSummary;
    step: LockStep | null;
    onClose: () => void;
    onReadyToLock: () => void;
    recoveryKeySet: boolean;
}) {
    const closeOnDismiss = (open: boolean) => !open && onClose();

    return (
        <>
            <SetPassphraseDialog
                vault={vault}
                open={step === 'passphrase'}
                onOpenChange={closeOnDismiss}
                onDone={onReadyToLock}
            />
            <UnlockDialog
                vault={vault}
                open={step === 'unlock'}
                onOpenChange={closeOnDismiss}
                onUnlocked={onReadyToLock}
            />
            <ChangePassphraseDialog
                vault={vault}
                open={step === 'change-passphrase'}
                onOpenChange={closeOnDismiss}
            />
            <RecoveryKeyDialog
                vault={vault}
                open={step === 'recovery-key'}
                onOpenChange={closeOnDismiss}
                replacing={recoveryKeySet}
                onCreated={() => router.reload({ only: ['recovery_key_set'] })}
            />
        </>
    );
}
