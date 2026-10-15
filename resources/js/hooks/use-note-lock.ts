import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useVaultUnlocked } from '@/components/kite/lock-dialogs';
import { isUnlocked, onBeforeVaultLock } from '@/lib/vault-keys';
import { update as updateLock } from '@/routes/note-lock';
import type { NoteDetail, VaultSummary } from '@/types';

/** The dialog the lock flow is showing, if any. */
export type LockStep =
    | 'passphrase'
    | 'unlock'
    | 'change-passphrase'
    | 'recovery-key';

/**
 * Locking the open note and taking its lock off. Locking asks for a passphrase first when the
 * vault has none, or for the passphrase when the vault is locked. A locked note is saved before
 * its vault locks, and closes once it has.
 */
export function useNoteLock(
    vault: VaultSummary,
    note: NoteDetail,
    save: () => Promise<unknown>,
    passphraseSet: boolean,
) {
    const locked = note.locked === true;
    const vaultUnlocked = useVaultUnlocked(vault.id);
    const [step, setStep] = useState<LockStep | null>(null);

    useEffect(
        () =>
            onBeforeVaultLock((vaultId) =>
                locked && vaultId === vault.id ? save() : undefined,
            ),
        [locked, vault.id, save],
    );

    useEffect(() => {
        if (locked && !vaultUnlocked) {
            router.reload();
        }
    }, [locked, vaultUnlocked]);

    const lockUrl = updateLock({ vault: vault.id, note: note.id }).url;

    const lockNow = () =>
        void save().then(() =>
            router.put(lockUrl, {}, { preserveScroll: true }),
        );

    const lockNote = () => {
        if (!passphraseSet) {
            setStep('passphrase');
        } else if (!isUnlocked(vault.id)) {
            setStep('unlock');
        } else {
            lockNow();
        }
    };

    const removeLock = () =>
        void save().then(() =>
            router.delete(lockUrl, { preserveScroll: true }),
        );

    return { locked, step, setStep, lockNow, lockNote, removeLock };
}
