import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
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
import { noteFolder } from '@/lib/note-tree';
import { destroy, store, update } from '@/routes/folders';
import type { VaultSummary } from '@/types';

/** Which folder dialog is open, and for which folder ('' is the vault root). */
export type FolderDialogState =
    | { kind: 'new'; path: string }
    | { kind: 'rename'; path: string }
    | { kind: 'delete'; path: string }
    | null;

type Props = {
    vault: VaultSummary;
    state: FolderDialogState;
    /** Notes inside the folder being deleted, so the confirmation can say what goes with it. */
    noteCount: (folder: string) => number;
    /** Whether the open note lives inside the given folder. */
    containsActiveNote: (folder: string) => boolean;
    onClose: () => void;
};

function folderName(path: string): string {
    return path.split('/').pop() ?? path;
}

export function FolderDialogs({
    vault,
    state,
    noteCount,
    containsActiveNote,
    onClose,
}: Props) {
    return (
        <Dialog
            open={state !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="folder-dialog"
            >
                {state?.kind === 'new' && (
                    <NewFolderForm
                        vault={vault}
                        parent={state.path}
                        onDone={onClose}
                    />
                )}
                {state?.kind === 'rename' && (
                    <RenameFolderForm
                        vault={vault}
                        path={state.path}
                        onDone={onClose}
                    />
                )}
                {state?.kind === 'delete' && (
                    <DeleteFolderConfirmation
                        vault={vault}
                        path={state.path}
                        noteCount={noteCount(state.path)}
                        leave={containsActiveNote(state.path)}
                        onDone={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function NameField({
    value,
    error,
    hint,
    onChange,
}: {
    value: string;
    error?: string;
    hint?: ReactNode;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor="folder-name">Name</Label>
            <Input
                id="folder-name"
                autoFocus
                autoComplete="off"
                placeholder="Projects"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Actions({
    label,
    processing,
    disabled = false,
    destructive = false,
}: {
    label: string;
    processing: boolean;
    disabled?: boolean;
    destructive?: boolean;
}) {
    return (
        <DialogFooter>
            <DialogClose asChild>
                <Button type="button" variant="outline">
                    Cancel
                </Button>
            </DialogClose>
            <Button
                type="submit"
                variant={destructive ? 'destructive' : 'default'}
                disabled={processing || disabled}
            >
                {processing && <Spinner />}
                {label}
            </Button>
        </DialogFooter>
    );
}

function NewFolderForm({
    vault,
    parent,
    onDone,
}: {
    vault: VaultSummary;
    parent: string;
    onDone: () => void;
}) {
    const form = useForm({ name: '', parent });

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(store(vault.id).url, {
                    only: ['folders'],
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: onDone,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>New folder</DialogTitle>
                <DialogDescription>
                    {parent === ''
                        ? `Create a folder in ${vault.name}.`
                        : `Create a folder inside ${parent}.`}
                </DialogDescription>
            </DialogHeader>
            <NameField
                value={form.data.name}
                error={form.errors.name}
                hint="Use / to create nested folders, e.g. Projects/2026."
                onChange={(value) => form.setData('name', value)}
            />
            <Actions
                label="Create folder"
                processing={form.processing}
                disabled={form.data.name.trim() === ''}
            />
        </form>
    );
}

function RenameFolderForm({
    vault,
    path,
    onDone,
}: {
    vault: VaultSummary;
    path: string;
    onDone: () => void;
}) {
    const parent = noteFolder(path);
    const form = useForm({ path, name: folderName(path) });

    form.transform((data) => ({
        path: data.path,
        to: parent === '' ? data.name.trim() : `${parent}/${data.name.trim()}`,
    }));

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(update(vault.id).url, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: onDone,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Rename folder</DialogTitle>
                <DialogDescription>
                    Notes inside keep their links: any link that names them by
                    path is updated.
                </DialogDescription>
            </DialogHeader>
            <NameField
                value={form.data.name}
                error={(form.errors as Record<string, string | undefined>).to}
                onChange={(value) => form.setData('name', value)}
            />
            <Actions
                label="Rename"
                processing={form.processing}
                disabled={
                    form.data.name.trim() === '' ||
                    form.data.name.trim() === folderName(path)
                }
            />
        </form>
    );
}

function DeleteFolderConfirmation({
    vault,
    path,
    noteCount,
    leave,
    onDone,
}: {
    vault: VaultSummary;
    path: string;
    noteCount: number;
    leave: boolean;
    onDone: () => void;
}) {
    const { native } = usePage().props.kite;
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string>();

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                router.delete(destroy(vault.id).url, {
                    data: leave ? { path, leave: true } : { path },
                    preserveScroll: true,
                    onStart: () => setProcessing(true),
                    onFinish: () => setProcessing(false),
                    onSuccess: onDone,
                    onError: (errors) =>
                        setError(errors.path ?? 'Could not delete the folder.'),
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Delete “{folderName(path)}”?</DialogTitle>
                <DialogDescription>
                    {noteCount === 0
                        ? 'The folder and anything in it'
                        : `The folder and its ${noteCount === 1 ? 'note' : `${noteCount} notes`}`}{' '}
                    {native
                        ? 'will be moved to the trash.'
                        : 'will be deleted.'}{' '}
                    Links to those notes will show as unresolved. Its notes can
                    be restored from Recently deleted, in the vault menu.
                </DialogDescription>
            </DialogHeader>
            <InputError message={error} />
            <Actions
                label="Delete folder"
                processing={processing}
                destructive
            />
        </form>
    );
}
