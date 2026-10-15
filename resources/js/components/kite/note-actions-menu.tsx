import {
    FileCode,
    FileDown,
    Focus,
    Globe,
    History,
    KeyRound,
    LockKeyhole,
    LockKeyholeOpen,
    Merge,
    MoreHorizontal,
    PencilLine,
    Trash2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatShortcut } from '@/lib/shortcuts';

type Props = {
    locked: boolean;
    /** Running in the desktop app, which can print to PDF. */
    native: boolean;
    platform: string;
    passphraseSet: boolean;
    recoveryKeySet: boolean;
    onRename: () => void;
    onFocus: () => void;
    onMerge: () => void;
    onHistory: () => void;
    onLockNote: () => void;
    onRemoveLock: () => void;
    onLockVault: () => void;
    onChangePassphrase: () => void;
    onRecoveryKey: () => void;
    onExport: (format: 'html' | 'pdf') => void;
    onPublish: () => void;
    onDelete: () => void;
};

/**
 * The note's "…" menu. Locked notes can't be merged, exported or published.
 */
export function NoteActionsMenu({
    locked,
    native,
    platform,
    passphraseSet,
    recoveryKeySet,
    onRename,
    onFocus,
    onMerge,
    onHistory,
    onLockNote,
    onRemoveLock,
    onLockVault,
    onChangePassphrase,
    onRecoveryKey,
    onExport,
    onPublish,
    onDelete,
}: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-label="Note actions"
                    data-test="note-actions"
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onRename}>
                    <PencilLine />
                    Rename or move
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={onFocus} data-test="enter-focus">
                    <Focus />
                    Focus mode
                    <DropdownMenuShortcut>
                        {formatShortcut('Mod+Shift+F', platform)}
                    </DropdownMenuShortcut>
                </DropdownMenuItem>
                {!locked && (
                    <DropdownMenuItem onSelect={onMerge} data-test="merge-note">
                        <Merge />
                        Merge into…
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onSelect={onHistory} data-test="open-history">
                    <History />
                    Version history
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                {locked ? (
                    <>
                        <DropdownMenuItem
                            onSelect={onRemoveLock}
                            data-test="remove-lock"
                        >
                            <LockKeyholeOpen />
                            Remove lock
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={onLockVault}
                            data-test="lock-vault"
                        >
                            <LockKeyhole />
                            Lock vault now
                        </DropdownMenuItem>
                    </>
                ) : (
                    <DropdownMenuItem
                        onSelect={onLockNote}
                        data-test="lock-note"
                    >
                        <LockKeyhole />
                        Lock note…
                    </DropdownMenuItem>
                )}
                {passphraseSet && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger data-test="vault-passphrase-menu">
                            <KeyRound />
                            Vault passphrase
                        </DropdownMenuSubTrigger>
                        <DropdownMenuSubContent>
                            <DropdownMenuItem
                                onSelect={onChangePassphrase}
                                data-test="change-passphrase"
                            >
                                Change passphrase…
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={onRecoveryKey}
                                data-test="new-recovery-key"
                            >
                                {recoveryKeySet
                                    ? 'New recovery key…'
                                    : 'Create recovery key…'}
                            </DropdownMenuItem>
                        </DropdownMenuSubContent>
                    </DropdownMenuSub>
                )}
                {!locked && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => onExport('html')}
                            data-test="export-html"
                        >
                            <FileCode />
                            Export as HTML
                        </DropdownMenuItem>
                        {native && (
                            <DropdownMenuItem
                                onSelect={() => onExport('pdf')}
                                data-test="export-pdf"
                            >
                                <FileDown />
                                Export as PDF
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            onSelect={onPublish}
                            data-test="publish-note"
                        >
                            <Globe />
                            Publish as a web page
                        </DropdownMenuItem>
                    </>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    onSelect={onDelete}
                    data-test="delete-note"
                >
                    <Trash2 />
                    Delete note
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
