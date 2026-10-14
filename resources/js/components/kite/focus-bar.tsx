import { Check, Target, X } from 'lucide-react';
import { useState } from 'react';
import { Kbd } from '@/components/kite/kbd';
import { cn } from '@/lib/utils';

const FRONTMATTER = /^---[ \t]*\n[\s\S]*?\n?(?:---|\.\.\.)[ \t]*(?:\n|$)/;

/** Words in a note, counted like the index does, leaving out its frontmatter. */
export function countWords(contents: string): number {
    return (
        contents
            .replace(FRONTMATTER, '')
            .match(/[\p{L}\p{N}][\p{L}\p{N}'’_-]*/gu)?.length ?? 0
    );
}

type Props = {
    words: number;
    /** The note's `word_goal` property, if it has one. */
    goal: number | null;
    onGoalChange: (goal: number | null) => void;
    onExit: () => void;
};

/**
 * The strip along the bottom of focus mode: the word count, progress towards the note's word
 * goal (kept in its `word_goal` property), and a way out.
 */
export function FocusBar({ words, goal, onGoalChange, onExit }: Props) {
    const [editing, setEditing] = useState(false);
    const progress = goal ? Math.min(words / goal, 1) : 0;
    const reached = goal !== null && words >= goal;

    const commit = (value: string) => {
        const next = Math.round(Number(value));

        setEditing(false);
        onGoalChange(Number.isFinite(next) && next > 0 ? next : null);
    };

    return (
        <div
            className="pointer-events-none absolute inset-x-0 bottom-5 z-10 flex justify-center"
            data-test="focus-bar"
        >
            <div className="pointer-events-auto flex items-center gap-3 rounded-full border bg-popover/90 px-4 py-1.5 text-xs text-muted-foreground shadow-kite-lg backdrop-blur">
                <span className="tabular-nums" data-test="focus-words">
                    <span className="font-medium text-foreground">
                        {words.toLocaleString()}
                    </span>
                    {goal !== null && ` / ${goal.toLocaleString()}`} words
                </span>

                {goal !== null && !editing && (
                    <span
                        className="h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-label="Word goal"
                        aria-valuemin={0}
                        aria-valuemax={goal}
                        aria-valuenow={Math.min(words, goal)}
                    >
                        <span
                            className={cn(
                                'block h-full rounded-full bg-primary transition-[width]',
                                reached && 'bg-emerald-500',
                            )}
                            style={{ width: `${progress * 100}%` }}
                        />
                    </span>
                )}

                {editing ? (
                    <input
                        autoFocus
                        type="number"
                        min={1}
                        step={50}
                        aria-label="Word goal"
                        defaultValue={goal ?? 500}
                        onBlur={(event) => commit(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                commit(event.currentTarget.value);
                            } else if (event.key === 'Escape') {
                                event.stopPropagation();
                                setEditing(false);
                            }
                        }}
                        className="h-6 w-20 rounded-md border bg-background px-1.5 text-foreground tabular-nums outline-none"
                        data-test="focus-goal-input"
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className={cn(
                            'flex items-center gap-1 rounded-full px-1.5 py-0.5 hover:bg-accent hover:text-foreground',
                            reached && 'text-emerald-600 dark:text-emerald-400',
                        )}
                        data-test="focus-goal"
                    >
                        {reached ? (
                            <Check className="size-3.5" />
                        ) : (
                            <Target className="size-3.5" />
                        )}
                        {goal === null
                            ? 'Set a goal'
                            : reached
                              ? 'Goal reached'
                              : 'Edit goal'}
                    </button>
                )}

                <span className="h-4 w-px bg-border" />

                <button
                    type="button"
                    onClick={onExit}
                    className="flex items-center gap-1.5 rounded-full py-0.5 pr-0.5 pl-1.5 hover:text-foreground"
                    data-test="exit-focus"
                >
                    <X className="size-3.5" />
                    Exit
                    <Kbd shortcut="Esc" />
                </button>
            </div>
        </div>
    );
}
