import { useForm } from '@inertiajs/react';
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
import { store } from '@/routes/folders';
import type { VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    /** Folder to create the new one in; null while closed, '' for the vault root. */
    parent: string | null;
    onClose: () => void;
};

export function NewFolderDialog({ vault, parent, onClose }: Props) {
    return (
        <Dialog
            open={parent !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="new-folder-dialog"
            >
                {parent !== null && (
                    <NewFolderForm
                        vault={vault}
                        parent={parent}
                        onCreated={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function NewFolderForm({
    vault,
    parent,
    onCreated,
}: {
    vault: VaultSummary;
    parent: string;
    onCreated: () => void;
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
                    onSuccess: onCreated,
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

            <div className="grid gap-1.5">
                <Label htmlFor="folder-name">Name</Label>
                <Input
                    id="folder-name"
                    autoFocus
                    autoComplete="off"
                    placeholder="Projects"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Use / to create nested folders, e.g. Projects/2026.
                </p>
                <InputError message={form.errors.name} />
            </div>

            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={form.processing || form.data.name.trim() === ''}
                >
                    {form.processing && <Spinner />}
                    Create folder
                </Button>
            </DialogFooter>
        </form>
    );
}
