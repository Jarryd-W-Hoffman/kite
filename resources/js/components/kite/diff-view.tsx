import { toHunks, type DiffLine } from '@/lib/line-diff';
import { cn } from '@/lib/utils';

/**
 * Lines added and removed between two texts, with unchanged stretches folded away.
 */
export function DiffView({
    lines,
    empty,
}: {
    lines: DiffLine[];
    empty: string;
}) {
    const hunks = toHunks(lines);
    const changed = lines.some((line) => line.type !== 'same');

    if (!changed) {
        return (
            <div className="flex-1 overflow-y-auto p-4">
                <p className="mb-3 text-sm text-muted-foreground">{empty}</p>
                <pre className="font-mono text-xs whitespace-pre-wrap text-muted-foreground">
                    {lines.map((line) => line.text).join('\n')}
                </pre>
            </div>
        );
    }

    return (
        <div
            className="flex-1 overflow-y-auto py-2 font-mono text-xs"
            data-test="diff"
        >
            {hunks.map((hunk, index) =>
                hunk.type === 'skipped' ? (
                    <div
                        key={index}
                        className="my-1 bg-muted/60 px-4 py-1 text-muted-foreground"
                    >
                        ⋯ {hunk.count} unchanged{' '}
                        {hunk.count === 1 ? 'line' : 'lines'}
                    </div>
                ) : (
                    hunk.lines.map((line, lineIndex) => (
                        <div
                            key={`${index}:${lineIndex}`}
                            data-diff={line.type}
                            className={cn(
                                'px-4 break-words whitespace-pre-wrap',
                                line.type === 'added' &&
                                    'bg-emerald-500/15 text-emerald-900 dark:text-emerald-200',
                                line.type === 'removed' &&
                                    'bg-red-500/15 text-red-900 line-through decoration-red-500/40 dark:text-red-200',
                            )}
                        >
                            <span className="mr-3 inline-block w-3 text-muted-foreground select-none">
                                {line.type === 'added'
                                    ? '+'
                                    : line.type === 'removed'
                                      ? '−'
                                      : ' '}
                            </span>
                            {line.text === '' ? ' ' : line.text}
                        </div>
                    ))
                ),
            )}
        </div>
    );
}
