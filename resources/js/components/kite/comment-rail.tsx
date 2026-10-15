import {
    Check,
    MessageSquare,
    Pencil,
    RotateCcw,
    Trash2,
    Unlink,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { NoteComments } from '@/hooks/use-note-comments';
import { cn } from '@/lib/utils';
import type { NoteComment } from '@/types';

type Props = {
    notes: NoteComments;
    /** Why comments are unavailable, e.g. the note's comments file is damaged. */
    error?: string;
    onSelect: (id: string) => void;
    onClose: () => void;
};

const textareaClass =
    'min-h-20 w-full resize-y rounded-md border bg-background px-2.5 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring';

/**
 * Comments beside the note, in the order their passages appear. Comments whose text can no longer
 * be found are listed as orphaned, and resolved ones are tucked away until asked for.
 */
export function CommentRail({ notes, error, onSelect, onClose }: Props) {
    const [showResolved, setShowResolved] = useState(false);
    const { comments, ranges, draft } = notes;
    const open = comments.filter((comment) => !comment.resolved);
    const resolved = comments.filter((comment) => comment.resolved);
    const anchored = ranges
        ? open
              .filter((comment) => ranges.has(comment.id))
              .sort(
                  (a, b) =>
                      (ranges.get(a.id)?.from ?? 0) -
                      (ranges.get(b.id)?.from ?? 0),
              )
        : open;
    const orphaned = ranges
        ? open.filter((comment) => !ranges.has(comment.id))
        : [];

    return (
        <aside
            aria-label="Comments"
            className="flex w-80 shrink-0 flex-col border-l border-border/70 bg-sidebar/40"
            data-test="comment-rail"
        >
            <div className="flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-4">
                <h2 className="text-[13.5px] font-semibold">Comments</h2>
                <span className="rounded-full bg-muted px-1.5 text-[11px] font-medium text-muted-foreground tabular-nums">
                    {open.length}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="ml-auto size-7"
                    aria-label="Close comments"
                    onClick={onClose}
                >
                    <X />
                </Button>
            </div>

            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                {error && (
                    <p
                        className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive"
                        role="alert"
                        data-test="comments-error"
                    >
                        {error}
                    </p>
                )}
                {draft && <DraftCard notes={notes} quote={draft.quote} />}

                {anchored.map((comment) => (
                    <CommentCard
                        key={comment.id}
                        comment={comment}
                        notes={notes}
                        onSelect={onSelect}
                    />
                ))}

                {!draft && !error && open.length === 0 && (
                    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center text-sm text-muted-foreground">
                        <MessageSquare className="size-6" />
                        <p>
                            No open comments. Select text in the editor and
                            choose <strong>Comment</strong>.
                        </p>
                    </div>
                )}

                {orphaned.length > 0 && (
                    <Section
                        title="Orphaned"
                        hint="The text these pointed to is no longer in the note."
                        testId="orphaned-comments"
                    >
                        {orphaned.map((comment) => (
                            <CommentCard
                                key={comment.id}
                                comment={comment}
                                notes={notes}
                                onSelect={onSelect}
                                orphaned
                            />
                        ))}
                    </Section>
                )}

                {resolved.length > 0 && (
                    <div className="pt-1">
                        <button
                            type="button"
                            onClick={() => setShowResolved((value) => !value)}
                            className="text-xs text-muted-foreground hover:text-foreground"
                            data-test="toggle-resolved"
                        >
                            {showResolved ? 'Hide' : 'Show'} resolved (
                            {resolved.length})
                        </button>
                        {showResolved && (
                            <div className="mt-3 space-y-3">
                                {resolved.map((comment) => (
                                    <CommentCard
                                        key={comment.id}
                                        comment={comment}
                                        notes={notes}
                                        onSelect={onSelect}
                                        orphaned={
                                            ranges !== null &&
                                            !ranges.has(comment.id)
                                        }
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </aside>
    );
}

function Section({
    title,
    hint,
    testId,
    children,
}: {
    title: string;
    hint: string;
    testId: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3 pt-2" data-test={testId}>
            <div>
                <h3 className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <Unlink className="size-3" />
                    {title}
                </h3>
                <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>
            </div>
            {children}
        </section>
    );
}

function Quote({ text, muted = false }: { text: string; muted?: boolean }) {
    return (
        <blockquote
            className={cn(
                'line-clamp-2 border-l-2 border-[var(--kite-comment)] pl-2 text-xs text-muted-foreground italic',
                muted && 'border-muted-foreground/40 line-through',
            )}
        >
            {text}
        </blockquote>
    );
}

function DraftCard({ notes, quote }: { notes: NoteComments; quote: string }) {
    const [body, setBody] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = async () => {
        if (body.trim() === '' || processing) {
            return;
        }

        setProcessing(true);
        await notes.add(body);
        setProcessing(false);
    };

    return (
        <form
            className="space-y-2 rounded-xl border border-primary/30 bg-background p-3 shadow-kite-lg ring-4 ring-primary/10"
            data-test="comment-draft"
            onSubmit={(event) => {
                event.preventDefault();
                void submit();
            }}
        >
            <Quote text={quote} />
            <textarea
                autoFocus
                aria-label="Comment"
                placeholder="Add a comment…"
                value={body}
                onChange={(event) => setBody(event.target.value)}
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        event.preventDefault();
                        void submit();
                    } else if (event.key === 'Escape') {
                        notes.cancelDraft();
                    }
                }}
                className={textareaClass}
            />
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={notes.cancelDraft}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={body.trim() === '' || processing}
                >
                    {processing && <Spinner />}
                    Comment
                </Button>
            </div>
        </form>
    );
}

function CommentCard({
    comment,
    notes,
    onSelect,
    orphaned = false,
}: {
    comment: NoteComment;
    notes: NoteComments;
    onSelect: (id: string) => void;
    orphaned?: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [body, setBody] = useState(comment.body);
    const active = notes.activeId === comment.id;

    const saveEdit = async () => {
        if (body.trim() === '') {
            return;
        }

        await notes.edit(comment.id, body);
        setEditing(false);
    };

    return (
        <article
            className={cn(
                'group space-y-2 rounded-xl border bg-background p-3 text-sm shadow-kite-sm transition-shadow hover:shadow-kite-lg',
                active && 'ring-2 ring-[var(--kite-comment)]',
                comment.resolved && 'opacity-70',
            )}
            data-test="comment"
            data-comment-id={comment.id}
            data-active={active}
        >
            <button
                type="button"
                className="block w-full text-left"
                onClick={() => !orphaned && onSelect(comment.id)}
                disabled={orphaned}
                title={orphaned ? undefined : 'Show in note'}
            >
                <Quote text={comment.quote} muted={orphaned} />
            </button>

            {editing ? (
                <form
                    className="space-y-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void saveEdit();
                    }}
                >
                    <textarea
                        autoFocus
                        aria-label="Edit comment"
                        value={body}
                        onChange={(event) => setBody(event.target.value)}
                        onKeyDown={(event) => {
                            if (
                                event.key === 'Enter' &&
                                (event.metaKey || event.ctrlKey)
                            ) {
                                event.preventDefault();
                                void saveEdit();
                            } else if (event.key === 'Escape') {
                                setEditing(false);
                                setBody(comment.body);
                            }
                        }}
                        className={textareaClass}
                    />
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setEditing(false);
                                setBody(comment.body);
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={body.trim() === ''}
                        >
                            Save
                        </Button>
                    </div>
                </form>
            ) : (
                <p
                    className="break-words whitespace-pre-wrap"
                    data-test="comment-body"
                >
                    {comment.body}
                </p>
            )}

            <div className="flex items-center gap-1">
                <time
                    dateTime={comment.created_at}
                    className="mr-auto text-xs text-muted-foreground"
                    title={new Date(comment.created_at).toLocaleString()}
                >
                    {formatWhen(comment.created_at)}
                </time>
                <IconButton
                    label={comment.resolved ? 'Reopen' : 'Resolve'}
                    onClick={() =>
                        void notes.setResolved(comment.id, !comment.resolved)
                    }
                >
                    {comment.resolved ? <RotateCcw /> : <Check />}
                </IconButton>
                {!editing && (
                    <IconButton label="Edit" onClick={() => setEditing(true)}>
                        <Pencil />
                    </IconButton>
                )}
                <IconButton
                    label="Delete"
                    onClick={() => void notes.remove(comment.id)}
                >
                    <Trash2 />
                </IconButton>
            </div>
        </article>
    );
}

function IconButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 text-muted-foreground"
            aria-label={label}
            title={label}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function formatWhen(iso: string): string {
    const date = new Date(iso);
    const minutes = Math.round((Date.now() - date.getTime()) / 60000);

    if (minutes < 1) {
        return 'Just now';
    }

    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        ...(date.getFullYear() === new Date().getFullYear()
            ? {}
            : { year: 'numeric' }),
    });
}
