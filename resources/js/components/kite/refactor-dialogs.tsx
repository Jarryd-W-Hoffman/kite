import { router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import { matchNotes, noteFolder } from '@/lib/note-tree';
import { cn } from '@/lib/utils';
import { store as storeExtraction } from '@/routes/note-extractions';
import { store as storeMerge } from '@/routes/note-merges';
import type { TreeNote, VaultSummary } from '@/types';

export type Extracted = {
    id: number;
    title: string;
    link: string;
    url: string;
};

/** A title suggested from the passage: its first line without markdown markers. */
export function suggestTitle(text: string): string {
    const line =
        text
            .split('\n')
            .map((candidate) =>
                candidate
                    .replace(
                        /^\s*(?:#{1,6}\s+|[-*+]\s+(?:\[[ xX]\]\s+)?|\d+[.)]\s+|>\s*)/,
                        '',
                    )
                    .replace(/[*_`~[\]]/g, '')
                    .trim(),
            )
            .find((candidate) => candidate !== '') ?? '';

    return line.length > 60 ? `${line.slice(0, 60).trimEnd()}…` : line;
}

/**
 * Name the note a selected passage moves into. The new note sits beside this one; the passage is
 * replaced with a link to it once it exists.
 */
export function ExtractNoteDialog({
    vault,
    note,
    text,
    open,
    onOpenChange,
    onExtracted,
}: {
    vault: VaultSummary;
    note: { id: number };
    text: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onExtracted: (extracted: Extracted) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="extract-dialog"
            >
                {open && (
                    <ExtractForm
                        vault={vault}
                        note={note}
                        text={text}
                        onExtracted={onExtracted}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ExtractForm({
    vault,
    note,
    text,
    onExtracted,
}: {
    vault: VaultSummary;
    note: { id: number };
    text: string;
    onExtracted: (extracted: Extracted) => void;
}) {
    const [title, setTitle] = useState(() => suggestTitle(text));
    const [processing, setProcessing] = useState(false);

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();

                if (title.trim() === '') {
                    return;
                }

                setProcessing(true);
                requestJson<Extracted>(
                    storeExtraction({ vault: vault.id, note: note.id }),
                    { title: title.trim(), text },
                )
                    .then(onExtracted)
                    .catch(() => toast.error('Could not extract the passage.'))
                    .finally(() => setProcessing(false));
            }}
        >
            <DialogHeader>
                <DialogTitle>Extract to a new note</DialogTitle>
                <DialogDescription>
                    The selected text moves into a new note beside this one, and
                    a link to it takes its place.
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-1.5">
                <Label htmlFor="extract-title">Title</Label>
                <Input
                    id="extract-title"
                    name="title"
                    value={title}
                    onChange={(event) => setTitle(event.target.value)}
                    autoFocus
                    onFocus={(event) => event.currentTarget.select()}
                />
            </div>
            <pre className="max-h-32 overflow-hidden rounded-md border bg-muted/40 [mask-image:linear-gradient(to_bottom,black_70%,transparent)] px-3 py-2 font-sans text-xs whitespace-pre-wrap text-muted-foreground">
                {text}
            </pre>
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={processing || title.trim() === ''}
                >
                    {processing && <Spinner />}
                    Extract
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * Pick the note this one merges into. Its text is appended there under a heading, links to it
 * are pointed at the other note, and it goes to the trash.
 */
export function MergeNoteDialog({
    vault,
    note,
    notes,
    open,
    onOpenChange,
    beforeMerge,
}: {
    vault: VaultSummary;
    note: { id: number; title: string };
    notes: TreeNote[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Runs before merging, e.g. to save unsaved edits first. */
    beforeMerge: () => Promise<unknown>;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="merge-dialog"
            >
                {open && (
                    <MergeForm
                        vault={vault}
                        note={note}
                        notes={notes.filter((other) => other.id !== note.id)}
                        beforeMerge={beforeMerge}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function MergeForm({
    vault,
    note,
    notes,
    beforeMerge,
    onDone,
}: {
    vault: VaultSummary;
    note: { id: number; title: string };
    notes: TreeNote[];
    beforeMerge: () => Promise<unknown>;
    onDone: () => void;
}) {
    const [query, setQuery] = useState('');
    const [target, setTarget] = useState<TreeNote | null>(null);
    const [processing, setProcessing] = useState(false);
    const matches = matchNotes(notes, query, 6);

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();

                if (!target) {
                    return;
                }

                setProcessing(true);
                void beforeMerge().then(() =>
                    router.post(
                        storeMerge({ vault: vault.id, note: note.id }).url,
                        { into: target.id },
                        {
                            onSuccess: onDone,
                            onFinish: () => setProcessing(false),
                        },
                    ),
                );
            }}
        >
            <DialogHeader>
                <DialogTitle>Merge “{note.title}” into…</DialogTitle>
                <DialogDescription>
                    Its text is added to the end of the note you choose, links
                    to it are updated, and this note moves to the trash.
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-1.5">
                <Input
                    aria-label="Find a note"
                    placeholder="Find a note…"
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setTarget(null);
                    }}
                    autoFocus
                    data-test="merge-search"
                />
                <ul className="grid gap-0.5" role="listbox">
                    {matches.map((match) => (
                        <li key={match.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={target?.id === match.id}
                                onClick={() => setTarget(match)}
                                className={cn(
                                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent',
                                    target?.id === match.id &&
                                        'bg-kite-accent-soft text-primary hover:bg-kite-accent-soft',
                                )}
                                data-test="merge-option"
                            >
                                <FileText className="size-4 shrink-0 text-muted-foreground" />
                                <span className="truncate font-medium">
                                    {match.title}
                                </span>
                                {noteFolder(match.path) !== '' && (
                                    <span className="ml-auto truncate text-xs text-muted-foreground">
                                        {noteFolder(match.path)}
                                    </span>
                                )}
                            </button>
                        </li>
                    ))}
                    {matches.length === 0 && (
                        <li className="px-2 py-3 text-center text-sm text-muted-foreground">
                            No notes found.
                        </li>
                    )}
                </ul>
            </div>
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={processing || !target}>
                    {processing && <Spinner />}
                    {target ? `Merge into “${target.title}”` : 'Merge'}
                </Button>
            </DialogFooter>
        </form>
    );
}
