import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/notes';
import type { VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    note: { id: number; title: string };
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Confirm deleting a note, saying where the file goes: the trash in the desktop app, gone otherwise.
 */
export function DeleteNoteDialog({ vault, note, open, onOpenChange }: Props) {
    const { kite } = usePage().props;
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="delete-note-dialog"
            >
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.delete(
                            destroy({ vault: vault.id, note: note.id }).url,
                            {
                                onStart: () => setProcessing(true),
                                onFinish: () => setProcessing(false),
                            },
                        );
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Delete “{note.title}”?</DialogTitle>
                        <DialogDescription>
                            The file{' '}
                            {kite.native
                                ? 'will be moved to the trash.'
                                : 'will be deleted.'}{' '}
                            Links to it will show as unresolved. You can restore
                            it from Recently deleted, in the vault menu.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Delete note
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
