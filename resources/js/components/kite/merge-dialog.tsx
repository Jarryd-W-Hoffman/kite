import { GitMerge } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    type MergeChoice,
    type MergeChunk,
    mergeThreeWay,
    mergeTwoWay,
    resolveMerge,
} from '@/lib/merge';
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    /** What both versions started from; null when that isn't known, so every difference is a choice. */
    base: string | null;
    mine: string;
    theirs: string;
    labels: { mine: string; theirs: string };
    saveLabel: string;
    onSave: (text: string) => Promise<void> | void;
};

/** Unchanged stretches longer than this are folded, keeping a line or two either side. */
const FOLD_AFTER = 6;

/**
 * Merge two versions of a note: changes only one side made are taken as they are, and where they
 * disagree, the person chooses one, the other or both.
 */
export function MergeDialog({ open, onOpenChange, ...props }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag flex h-[85vh] flex-col gap-0 p-0 sm:max-w-5xl"
                data-test="merge-dialog"
            >
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle className="flex items-center gap-2">
                        <GitMerge className="size-4" />
                        {props.title}
                    </DialogTitle>
                    <DialogDescription>{props.description}</DialogDescription>
                </DialogHeader>
                {open && <MergeEditor {...props} />}
            </DialogContent>
        </Dialog>
    );
}

function MergeEditor({
    base,
    mine,
    theirs,
    labels,
    saveLabel,
    onSave,
}: Omit<Props, 'open' | 'onOpenChange' | 'title' | 'description'>) {
    const chunks = useMemo(
        () =>
            base === null
                ? mergeTwoWay(mine, theirs)
                : mergeThreeWay(base, mine, theirs),
        [base, mine, theirs],
    );
    const conflictCount = chunks.filter(
        (chunk) => chunk.type === 'conflict',
    ).length;
    const autoMerged = chunks.filter(
        (chunk) =>
            chunk.type === 'clean' &&
            (chunk.source === 'mine' || chunk.source === 'theirs'),
    ).length;
    const [choices, setChoices] = useState<(MergeChoice | null)[]>(() =>
        Array.from({ length: conflictCount }, () => null),
    );
    const [saving, setSaving] = useState(false);
    const unresolved = choices.filter((choice) => choice === null).length;

    const choose = (index: number, choice: MergeChoice) =>
        setChoices((current) =>
            current.map((value, position) =>
                position === index ? choice : value,
            ),
        );

    const save = async () => {
        setSaving(true);

        try {
            await onSave(
                resolveMerge(
                    chunks,
                    choices.map((choice) => choice ?? 'mine'),
                ),
            );
        } finally {
            setSaving(false);
        }
    };

    let conflictIndex = 0;

    return (
        <>
            <div className="flex flex-wrap items-center gap-2 border-b px-6 py-2.5 text-sm">
                <p className="min-w-0 flex-1 text-muted-foreground">
                    {conflictCount === 0
                        ? 'Everything merged by itself.'
                        : `${conflictCount} ${conflictCount === 1 ? 'place differs' : 'places differ'}: choose which to keep.`}
                    {autoMerged > 0 &&
                        ` ${autoMerged} ${autoMerged === 1 ? 'change was' : 'changes were'} taken as they are.`}
                </p>
                {conflictCount > 0 && (
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setChoices(choices.map(() => 'mine'))
                            }
                            data-test="merge-all-mine"
                        >
                            Use {labels.mine.toLowerCase()} everywhere
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setChoices(choices.map(() => 'theirs'))
                            }
                            data-test="merge-all-theirs"
                        >
                            Use {labels.theirs.toLowerCase()} everywhere
                        </Button>
                    </>
                )}
            </div>

            <div className="flex-1 space-y-2 overflow-y-auto px-6 py-4 font-mono text-xs">
                {chunks.map((chunk, index) => {
                    if (chunk.type === 'clean') {
                        return (
                            <CleanChunk
                                key={index}
                                chunk={chunk}
                                labels={labels}
                            />
                        );
                    }

                    const position = conflictIndex++;

                    return (
                        <ConflictChunk
                            key={index}
                            chunk={chunk}
                            labels={labels}
                            choice={choices[position] ?? null}
                            onChoose={(choice) => choose(position, choice)}
                        />
                    );
                })}
            </div>

            <DialogFooter className="items-center border-t px-6 py-3">
                {unresolved > 0 && (
                    <p className="mr-auto text-sm text-muted-foreground">
                        {unresolved} still to choose
                    </p>
                )}
                <Button
                    onClick={() => void save()}
                    disabled={unresolved > 0 || saving}
                    data-test="merge-save"
                >
                    {saving && <Spinner />}
                    {saveLabel}
                </Button>
            </DialogFooter>
        </>
    );
}

function Lines({ lines }: { lines: string[] }) {
    return (
        <pre className="break-words whitespace-pre-wrap">
            {lines.map((line) => (line === '' ? ' ' : line)).join('\n')}
        </pre>
    );
}

function CleanChunk({
    chunk,
    labels,
}: {
    chunk: Extract<MergeChunk, { type: 'clean' }>;
    labels: Props['labels'];
}) {
    if (chunk.source === 'base' || chunk.source === 'both') {
        const folded = chunk.lines.length > FOLD_AFTER;

        return (
            <div className="px-3 text-muted-foreground">
                <Lines lines={folded ? chunk.lines.slice(0, 2) : chunk.lines} />
                {folded && (
                    <>
                        <div className="my-1 rounded bg-muted/60 px-2 py-0.5 font-sans">
                            ⋯ {chunk.lines.length - 4} unchanged lines
                        </div>
                        <Lines lines={chunk.lines.slice(-2)} />
                    </>
                )}
            </div>
        );
    }

    return (
        <div
            className="rounded-md border-l-2 border-emerald-500/60 bg-emerald-500/10 px-3 py-1.5"
            data-test="merge-auto"
        >
            <div className="mb-1 font-sans text-[11px] text-muted-foreground">
                From{' '}
                {chunk.source === 'mine'
                    ? labels.mine.toLowerCase()
                    : labels.theirs.toLowerCase()}
            </div>
            <Lines lines={chunk.lines} />
        </div>
    );
}

function ConflictChunk({
    chunk,
    labels,
    choice,
    onChoose,
}: {
    chunk: Extract<MergeChunk, { type: 'conflict' }>;
    labels: Props['labels'];
    choice: MergeChoice | null;
    onChoose: (choice: MergeChoice) => void;
}) {
    const side = (which: 'mine' | 'theirs') => {
        const chosen = choice === which || choice === 'both';

        return (
            <button
                type="button"
                onClick={() => onChoose(which)}
                aria-pressed={choice === which}
                data-test={`merge-use-${which}`}
                className={cn(
                    'min-w-0 rounded-md border px-3 py-2 text-left transition-colors hover:bg-accent',
                    chosen
                        ? 'border-primary bg-kite-accent-soft hover:bg-kite-accent-soft'
                        : choice !== null && 'opacity-50',
                )}
            >
                <div className="mb-1 font-sans text-[11px] font-medium text-muted-foreground">
                    {labels[which]}
                </div>
                {chunk[which].length === 0 ? (
                    <span className="font-sans text-muted-foreground italic">
                        Nothing here
                    </span>
                ) : (
                    <Lines lines={chunk[which]} />
                )}
            </button>
        );
    };

    return (
        <div
            className={cn(
                'space-y-2 rounded-lg border p-2',
                choice === null
                    ? 'border-amber-500/40 bg-amber-500/5'
                    : 'border-border',
            )}
            data-test="merge-conflict"
        >
            <div className="grid grid-cols-2 gap-2">
                {side('mine')}
                {side('theirs')}
            </div>
            <div className="flex justify-end font-sans">
                <Button
                    variant={choice === 'both' ? 'secondary' : 'ghost'}
                    size="sm"
                    aria-pressed={choice === 'both'}
                    onClick={() => onChoose('both')}
                    data-test="merge-use-both"
                >
                    Keep both
                </Button>
            </div>
        </div>
    );
}
