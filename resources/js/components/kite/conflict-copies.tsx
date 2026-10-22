import { Link, router } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MergeDialog } from '@/components/kite/merge-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { requestJson } from '@/lib/kite-http';
import { destroy } from '@/routes/note-conflict-copies';
import { show as showPane } from '@/routes/note-panes';
import { show } from '@/routes/notes';
import type { NoteDetail, NoteLinkSummary, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    note: NoteDetail;
    /** This note's text in the editor now. */
    mine: string;
    /** Save the merged text as this note's, resolving to whether it was saved. */
    onMerged: (text: string) => Promise<boolean>;
};

/**
 * Tells about conflicting copies a sync app left beside the note (or, on such a copy, which note
 * it belongs to), and merges a copy into the note.
 */
export function ConflictCopies({ vault, note, mine, onMerged }: Props) {
    const copy = note.conflict_copies?.[0];

    if (note.conflict_of) {
        return <CopyNotice vault={vault} original={note.conflict_of} />;
    }

    if (!copy) {
        return null;
    }

    return (
        <CopyBanner
            key={copy.id}
            vault={vault}
            note={note}
            copy={copy}
            others={(note.conflict_copies?.length ?? 1) - 1}
            mine={mine}
            onMerged={onMerged}
        />
    );
}

function CopyBanner({
    vault,
    note,
    copy,
    others,
    mine,
    onMerged,
}: Omit<Props, 'note'> & {
    note: NoteDetail;
    copy: NoteLinkSummary;
    others: number;
}) {
    const [theirs, setTheirs] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const startMerge = async () => {
        setLoading(true);

        try {
            const { note: detail } = await requestJson<{ note: NoteDetail }>(
                showPane({ vault: vault.id, note: copy.id }),
            );

            if (detail.locked && !detail.unlocked) {
                toast.error('Unlock the vault to merge this copy.');

                return;
            }

            setTheirs(detail.contents);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div
            role="status"
            className="flex shrink-0 flex-wrap items-center gap-2 border-b border-sky-500/30 bg-sky-500/10 px-4 py-2.5 text-sm"
            data-test="conflict-copies"
        >
            <Copy className="size-4 shrink-0 text-sky-600 dark:text-sky-400" />
            <p className="min-w-0 flex-1">
                A sync app left a conflicting copy of this note:{' '}
                <span className="font-medium">{copy.path}</span>
                {others > 0 &&
                    ` (and ${others} more ${others === 1 ? 'copy' : 'copies'})`}
                .
            </p>
            <Button variant="ghost" size="sm" asChild>
                <Link href={show({ vault: vault.id, note: copy.id })}>
                    Open the copy
                </Link>
            </Button>
            <Button
                size="sm"
                onClick={() => void startMerge()}
                disabled={loading}
                data-test="merge-copy"
            >
                {loading && <Spinner />}
                Merge…
            </Button>

            <MergeDialog
                open={theirs !== null}
                onOpenChange={(open) => !open && setTheirs(null)}
                title={`Merge the copy into “${note.title}”`}
                description="Where the two differ, choose what to keep. The merged text is saved to this note, and the copy is deleted."
                base={null}
                mine={mine}
                theirs={theirs ?? ''}
                labels={{ mine: 'This note', theirs: 'The copy' }}
                saveLabel="Save and delete the copy"
                onSave={async (text) => {
                    const saved = await onMerged(text);

                    setTheirs(null);

                    // Not saved (the note changed on disk meanwhile): keep the copy for now.
                    if (!saved) {
                        return;
                    }

                    router.delete(
                        destroy({
                            vault: vault.id,
                            note: note.id,
                            copy: copy.id,
                        }).url,
                    );
                }}
            />
        </div>
    );
}

function CopyNotice({
    vault,
    original,
}: {
    vault: VaultSummary;
    original: NoteLinkSummary;
}) {
    return (
        <div
            role="status"
            className="flex shrink-0 flex-wrap items-center gap-2 border-b border-sky-500/30 bg-sky-500/10 px-4 py-2.5 text-sm"
            data-test="conflict-copy-of"
        >
            <Copy className="size-4 shrink-0 text-sky-600 dark:text-sky-400" />
            <p className="min-w-0 flex-1">
                This is a conflicting copy of{' '}
                <span className="font-medium">{original.title}</span>, left by a
                sync app.
            </p>
            <Button variant="ghost" size="sm" asChild>
                <Link href={show({ vault: vault.id, note: original.id })}>
                    Open it to merge this copy
                </Link>
            </Button>
        </div>
    );
}
