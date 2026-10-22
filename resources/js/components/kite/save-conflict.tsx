import { TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DiffView } from '@/components/kite/diff-view';
import { MergeDialog } from '@/components/kite/merge-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { diffLines } from '@/lib/line-diff';
import { cn } from '@/lib/utils';

type Props = {
    /** The note's text on disk now. */
    disk: string;
    /** This window's unsaved text. */
    mine: string;
    /** The text both started from: as this window last loaded or saved it. */
    base: string;
    onKeepMine: () => void;
    onTakeDisk: () => void;
    onMerge: (text: string) => void;
    compact?: boolean;
};

/**
 * Shown when a note changed on disk while it had unsaved edits here: autosave is paused until
 * one version is chosen or the two are merged, and they can be compared first.
 */
export function SaveConflictBanner({
    disk,
    mine,
    base,
    onKeepMine,
    onTakeDisk,
    onMerge,
    compact = false,
}: Props) {
    const [comparing, setComparing] = useState(false);
    const [merging, setMerging] = useState(false);
    const lines = useMemo(
        () => (comparing ? diffLines(disk, mine) : []),
        [comparing, disk, mine],
    );

    const choices = (
        <>
            <Button
                variant="outline"
                size="sm"
                onClick={() => {
                    setComparing(false);
                    onTakeDisk();
                }}
                data-test="conflict-take-disk"
            >
                Use the version on disk
            </Button>
            <Button
                size="sm"
                onClick={() => {
                    setComparing(false);
                    onKeepMine();
                }}
                data-test="conflict-keep-mine"
            >
                Keep mine
            </Button>
        </>
    );

    return (
        <div
            role="alert"
            className={cn(
                'flex shrink-0 flex-wrap items-center gap-2 border-b border-amber-500/30 bg-amber-500/10 text-sm',
                compact ? 'px-3 py-2' : 'px-4 py-2.5',
            )}
            data-test="save-conflict"
        >
            <TriangleAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <p className="min-w-0 flex-1">
                This note changed on disk while you were editing it. Your
                changes aren’t saved until you choose a version.
            </p>
            <div className="flex shrink-0 items-center gap-1.5">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setComparing(true)}
                    data-test="conflict-compare"
                >
                    Compare
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setMerging(true)}
                    data-test="conflict-merge"
                >
                    Merge…
                </Button>
                {choices}
            </div>

            <Dialog open={comparing} onOpenChange={setComparing}>
                <DialogContent className="kite-no-drag flex h-[80vh] flex-col gap-0 p-0 sm:max-w-4xl">
                    <DialogHeader className="border-b px-6 py-4">
                        <DialogTitle>Compare versions</DialogTitle>
                        <DialogDescription>
                            Removed lines are only in the version on disk; added
                            lines are only in yours.
                        </DialogDescription>
                    </DialogHeader>
                    <DiffView lines={lines} empty="The two versions match." />
                    <DialogFooter className="border-t px-6 py-3">
                        {choices}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <MergeDialog
                open={merging}
                onOpenChange={setMerging}
                title="Merge your changes"
                description="Changes made only here or only on disk are kept. Where both changed the same lines, choose what to keep."
                base={base}
                mine={mine}
                theirs={disk}
                labels={{ mine: 'Yours', theirs: 'On disk' }}
                saveLabel="Save merged note"
                onSave={(text) => {
                    setMerging(false);
                    onMerge(text);
                }}
            />
        </div>
    );
}
