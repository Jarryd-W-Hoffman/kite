import { router } from '@inertiajs/react';
import { LockKeyhole, RotateCcw, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/kite-http';
import { cn } from '@/lib/utils';
import { store as restore } from '@/routes/deleted-note-restores';
import { index, show } from '@/routes/deleted-notes';
import type { VaultSummary } from '@/types';

type DeletedNoteSummary = {
    id: number;
    title: string;
    path: string;
    locked: boolean;
    versions: number;
    deleted_at: string;
};

type Props = {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Notes deleted lately, in Kite or outside it, with their last text, to bring back with their
 * history.
 */
export function DeletedNotesDialog({ vault, open, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag flex h-[80vh] flex-col gap-0 p-0 sm:max-w-4xl"
                data-test="deleted-notes"
            >
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle>Recently deleted</DialogTitle>
                    <DialogDescription>
                        Deleted notes keep their history here for a while.
                        Restoring one puts it back where it was.
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <DeletedNotesBrowser
                        vault={vault}
                        onRestored={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function DeletedNotesBrowser({
    vault,
    onRestored,
}: {
    vault: VaultSummary;
    onRestored: () => void;
}) {
    const [notes, setNotes] = useState<DeletedNoteSummary[] | null>(null);
    const [keepDays, setKeepDays] = useState(30);
    const [selected, setSelected] = useState<number | null>(null);

    useEffect(() => {
        void requestJson<{
            deleted_notes: DeletedNoteSummary[];
            keep_days: number;
        }>(index(vault.id)).then((response) => {
            setNotes(response.deleted_notes);
            setKeepDays(response.keep_days);
            setSelected(response.deleted_notes[0]?.id ?? null);
        });
    }, [vault.id]);

    if (notes === null) {
        return (
            <div className="space-y-2 p-6" aria-busy="true">
                <Skeleton className="h-8 w-64 animate-pulse" />
                <Skeleton className="h-8 w-56 animate-pulse" />
                <Skeleton className="h-8 w-60 animate-pulse" />
            </div>
        );
    }

    if (notes.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center text-sm text-muted-foreground">
                <Trash2 className="size-8" />
                <p>No notes deleted in the last {keepDays} days.</p>
            </div>
        );
    }

    const note = notes.find((item) => item.id === selected);

    return (
        <div className="flex min-h-0 flex-1">
            <ul
                className="w-72 shrink-0 space-y-0.5 overflow-y-auto border-r bg-sidebar/50 p-2"
                data-test="deleted-note-list"
            >
                {notes.map((item) => (
                    <li key={item.id}>
                        <button
                            type="button"
                            onClick={() => setSelected(item.id)}
                            aria-current={item.id === selected}
                            data-test="deleted-note"
                            className={cn(
                                'w-full rounded-lg px-3 py-2 text-left text-[13px] transition-colors hover:bg-accent',
                                item.id === selected &&
                                    'bg-kite-accent-soft text-foreground hover:bg-kite-accent-soft',
                            )}
                        >
                            <span className="flex items-center gap-1.5 font-medium">
                                {item.locked && (
                                    <LockKeyhole className="size-3 shrink-0" />
                                )}
                                <span className="truncate">{item.title}</span>
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {item.path}
                            </span>
                            <span className="block text-xs text-muted-foreground">
                                Deleted{' '}
                                {new Date(item.deleted_at).toLocaleString(
                                    undefined,
                                    { dateStyle: 'medium', timeStyle: 'short' },
                                )}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            {note && (
                <DeletedNoteView
                    key={note.id}
                    vault={vault}
                    note={note}
                    onRestored={onRestored}
                />
            )}
        </div>
    );
}

function DeletedNoteView({
    vault,
    note,
    onRestored,
}: {
    vault: VaultSummary;
    note: DeletedNoteSummary;
    onRestored: () => void;
}) {
    const [contents, setContents] = useState<string | null>(null);
    const [restoring, setRestoring] = useState(false);

    useEffect(() => {
        void requestJson<{ contents: string }>(
            show({ vault: vault.id, deletedNote: note.id }),
        ).then((response) => setContents(response.contents));
    }, [vault.id, note.id]);

    const restoreNote = () =>
        router.post(
            restore({ vault: vault.id, deletedNote: note.id }).url,
            {},
            {
                onStart: () => setRestoring(true),
                onFinish: () => setRestoring(false),
                onSuccess: onRestored,
            },
        );

    return (
        <div className="flex min-w-0 flex-1 flex-col">
            <div className="flex items-center gap-3 border-b px-4 py-2.5">
                <p className="min-w-0 flex-1 truncate text-sm text-muted-foreground">
                    {note.versions === 1
                        ? 'Its last text is kept.'
                        : `Its last text and ${note.versions - 1} earlier ${
                              note.versions === 2 ? 'version' : 'versions'
                          } are kept.`}
                </p>
                <Button
                    size="sm"
                    onClick={restoreNote}
                    disabled={restoring}
                    data-test="restore-deleted-note"
                >
                    {restoring ? <Spinner /> : <RotateCcw />}
                    Restore
                </Button>
            </div>
            <div className="flex-1 overflow-y-auto p-4">
                {contents === null ? (
                    <div className="space-y-2" aria-busy="true">
                        <Skeleton className="h-4 w-3/4 animate-pulse" />
                        <Skeleton className="h-4 w-2/3 animate-pulse" />
                    </div>
                ) : note.locked && contents === '' ? (
                    <p className="text-sm text-muted-foreground">
                        This note is locked. Unlock the vault to read it, or
                        restore it and open it as usual.
                    </p>
                ) : (
                    <pre
                        className="font-mono text-xs whitespace-pre-wrap"
                        data-test="deleted-note-contents"
                    >
                        {contents}
                    </pre>
                )}
            </div>
        </div>
    );
}
