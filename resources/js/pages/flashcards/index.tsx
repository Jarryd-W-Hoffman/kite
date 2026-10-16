import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileText, GraduationCap, PartyPopper } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { NotePreview } from '@/components/kite/note-preview';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import { useVaultSync } from '@/hooks/use-vault-sync';
import { describeInterval, flashcardsUrl, localToday } from '@/lib/flashcards';
import { requestJson } from '@/lib/kite-http';
import { cn } from '@/lib/utils';
import { store as storeReview } from '@/routes/flashcard-reviews';
import { show as showNote } from '@/routes/notes';
import type { WorkspaceProps } from '@/types';

type Grade = 'again' | 'hard' | 'good' | 'easy';

type StudyCard = {
    key: string;
    kind: string;
    deck: string;
    front: string;
    back: string;
    note: { id: number; title: string; line: number };
    is_new: boolean;
    intervals: Record<Grade, number>;
};

type Deck = { name: string; due: number; new: number; total: number };

type Props = WorkspaceProps & {
    decks: Deck[];
    queue: StudyCard[];
    deck: string | null;
    today: string;
    studying: boolean;
};

const GRADES: { grade: Grade; label: string; className: string }[] = [
    {
        grade: 'again',
        label: 'Again',
        className: 'border-destructive/40 text-destructive-foreground',
    },
    { grade: 'hard', label: 'Hard', className: '' },
    {
        grade: 'good',
        label: 'Good',
        className: 'border-primary/50 text-primary',
    },
    { grade: 'easy', label: 'Easy', className: '' },
];

export default function FlashcardsIndex(props: Props) {
    const {
        vault,
        vaults,
        tree,
        folders,
        tags,
        indexing,
        watching,
        decks,
        deck,
        today,
        studying,
    } = props;
    const [paletteOpen, setPaletteOpen] = useState(false);

    useVaultSync(
        vault,
        () =>
            router.reload({
                only: ['decks', 'queue', 'tree', 'folders', 'tags'],
            }),
        watching,
    );

    // Opened without this computer's date (or left open past midnight): load today's cards.
    useEffect(() => {
        if (today !== localToday()) {
            router.visit(
                flashcardsUrl(vault.id, studying ? { deck } : undefined),
                { replace: true, preserveScroll: true },
            );
        }
    }, [today, vault.id, deck, studying]);

    return (
        <>
            <Head title={`Flashcards · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={props.colour_groups}
                    tags={tags}
                    activeNoteId={null}
                    flashcardsActive
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-4 text-[13px] text-muted-foreground">
                        <GraduationCap className="size-3.5" aria-hidden />
                        Flashcards
                        {studying && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="kite-no-drag ml-auto"
                                onClick={() =>
                                    router.visit(flashcardsUrl(vault.id))
                                }
                                data-test="flashcards-back"
                            >
                                <ArrowLeft />
                                Decks
                            </Button>
                        )}
                    </header>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {studying ? (
                            <Session
                                key={`${deck ?? ''}-${today}`}
                                {...props}
                            />
                        ) : decks.length === 0 ? (
                            <HowTo />
                        ) : (
                            <DeckList
                                decks={decks}
                                onStudy={(name) =>
                                    router.visit(
                                        flashcardsUrl(vault.id, { deck: name }),
                                    )
                                }
                            />
                        )}
                    </div>
                </main>
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />
        </>
    );
}

function DeckList({
    decks,
    onStudy,
}: {
    decks: Deck[];
    onStudy: (deck: string | null) => void;
}) {
    const due = decks.reduce((sum, deck) => sum + deck.due + deck.new, 0);

    return (
        <div className="mx-auto max-w-2xl px-8 pt-12 pb-24">
            <div className="mb-8 flex items-end justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Flashcards
                    </h1>
                    <p
                        className="text-sm text-muted-foreground"
                        data-test="flashcards-summary"
                    >
                        {due === 0
                            ? 'Nothing to review today.'
                            : `${due} ${due === 1 ? 'card' : 'cards'} to review today`}
                    </p>
                </div>
                {due > 0 && (
                    <Button onClick={() => onStudy(null)} data-test="study-all">
                        Study all
                    </Button>
                )}
            </div>
            <ul className="grid gap-2" data-test="decks">
                {decks.map((deck) => (
                    <li
                        key={deck.name}
                        className="flex items-center gap-4 rounded-xl border bg-card p-4 shadow-kite-sm"
                    >
                        <div className="min-w-0 flex-1">
                            <h2 className="truncate font-semibold">
                                {deck.name}
                            </h2>
                            <p className="text-xs text-muted-foreground tabular-nums">
                                {deck.total}{' '}
                                {deck.total === 1 ? 'card' : 'cards'}
                            </p>
                        </div>
                        <dl className="flex gap-4 text-center text-xs tabular-nums">
                            <div>
                                <dt className="text-muted-foreground">Due</dt>
                                <dd className="text-base font-semibold">
                                    {deck.due}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">New</dt>
                                <dd className="text-base font-semibold">
                                    {deck.new}
                                </dd>
                            </div>
                        </dl>
                        <Button
                            variant="outline"
                            disabled={deck.due + deck.new === 0}
                            onClick={() => onStudy(deck.name)}
                            aria-label={`Study ${deck.name}`}
                            data-test="study-deck"
                        >
                            Study
                        </Button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * One study session: each card's question, then its answer and how well it was remembered.
 * Space shows the answer; 1–4 grade it. A card marked "Again" comes back later in the session.
 */
function Session({ vault, queue: initial, today }: Props) {
    const [queue, setQueue] = useState(initial);
    const [revealed, setRevealed] = useState(false);
    const [saving, setSaving] = useState(false);
    const [reviewed, setReviewed] = useState(0);
    const card = queue[0];

    const grade = (choice: Grade) => {
        if (!card || saving) {
            return;
        }

        setSaving(true);
        requestJson<{ interval: number; intervals: Record<Grade, number> }>(
            storeReview(vault.id),
            {
                key: card.key,
                grade: choice,
                today,
            },
        )
            .then((response) => {
                setReviewed((count) => count + 1);
                setRevealed(false);
                setQueue((current) => {
                    const [, ...rest] = current;

                    return response.interval === 0
                        ? [
                              ...rest,
                              {
                                  ...card,
                                  is_new: false,
                                  intervals: response.intervals,
                              },
                          ]
                        : rest;
                });
            })
            .catch(() => toast.error('Could not save the review.'))
            .finally(() => setSaving(false));
    };

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.target as HTMLElement).closest(
                    'input, textarea, [contenteditable=true], [role=dialog]',
                )
            ) {
                return;
            }

            if (!revealed && (event.key === ' ' || event.key === 'Enter')) {
                event.preventDefault();
                setRevealed(true);
            } else if (revealed && ['1', '2', '3', '4'].includes(event.key)) {
                event.preventDefault();
                grade(GRADES[Number(event.key) - 1].grade);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    });

    if (!card) {
        return (
            <>
                <h1 className="sr-only">Flashcards</h1>
                <EmptyState
                    icon={PartyPopper}
                    title="All caught up"
                    description={
                        reviewed === 0
                            ? 'Nothing to review here today.'
                            : `You reviewed ${reviewed} ${reviewed === 1 ? 'card' : 'cards'}. Come back tomorrow for more.`
                    }
                    className="h-full"
                />
            </>
        );
    }

    return (
        <div className="mx-auto flex max-w-2xl flex-col px-8 pt-10 pb-24">
            <h1 className="sr-only">Studying {card.deck}</h1>
            <div className="mb-3 flex items-center gap-2 text-xs text-muted-foreground">
                <span
                    className="rounded-full bg-muted px-2 py-0.5 tabular-nums"
                    data-test="session-remaining"
                >
                    {queue.length} left
                </span>
                <span>{card.deck}</span>
                {card.is_new && (
                    <span className="rounded-full bg-kite-accent-soft px-2 py-0.5 text-primary">
                        New
                    </span>
                )}
                <Link
                    href={showNote({ vault: vault.id, note: card.note.id }).url}
                    className="ml-auto inline-flex items-center gap-1 hover:text-foreground"
                >
                    <FileText className="size-3.5" aria-hidden />
                    {card.note.title}
                </Link>
            </div>

            <section
                aria-label="Card"
                aria-live="polite"
                className="rounded-2xl border bg-card p-8 shadow-kite-sm"
                data-test="flashcard"
            >
                <NotePreview
                    vault={vault}
                    html={card.front}
                    className="p-0 text-lg"
                    testId="flashcard-front"
                />
                {revealed && (
                    <>
                        <hr className="my-6" />
                        <NotePreview
                            vault={vault}
                            html={card.back}
                            className="p-0 text-lg"
                            testId="flashcard-back"
                        />
                    </>
                )}
            </section>

            <div className="mt-6">
                {revealed ? (
                    <div
                        className="grid grid-cols-4 gap-2"
                        role="group"
                        aria-label="How well did you remember it?"
                    >
                        {GRADES.map(
                            ({ grade: choice, label, className }, index) => (
                                <Button
                                    key={choice}
                                    variant="outline"
                                    disabled={saving}
                                    className={cn(
                                        'h-auto flex-col gap-0.5 py-2',
                                        className,
                                    )}
                                    onClick={() => grade(choice)}
                                    data-test={`grade-${choice}`}
                                >
                                    <span className="font-semibold">
                                        {label}
                                    </span>
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {describeInterval(
                                            card.intervals[choice],
                                        )}{' '}
                                        · {index + 1}
                                    </span>
                                </Button>
                            ),
                        )}
                    </div>
                ) : (
                    <Button
                        className="w-full"
                        onClick={() => setRevealed(true)}
                        data-test="show-answer"
                    >
                        Show answer{' '}
                        <span className="text-xs opacity-80">Space</span>
                    </Button>
                )}
            </div>
        </div>
    );
}

function HowTo() {
    const examples: [string, string][] = [
        ['Capital of France :: Paris', 'A card with a question and an answer.'],
        ['perro ::: dog', 'Two cards, asking both ways.'],
        [
            'The ==mitochondria== is the powerhouse of the cell',
            'Hides each highlighted part in turn.',
        ],
        [
            'Name the planets\n?\nMercury, Venus, Earth…',
            'Longer questions and answers, around a line with just ?.',
        ],
    ];

    return (
        <div className="mx-auto max-w-2xl px-8 pt-12 pb-24">
            <EmptyState
                icon={GraduationCap}
                title="No flashcards yet"
                description="Tag a note #flashcards (or #flashcards/spanish for a deck of its own) and write cards in it. Kite brings each card back just before you'd forget it."
            />
            <ul className="mt-8 grid gap-3" data-test="flashcards-how-to">
                {examples.map(([syntax, meaning]) => (
                    <li key={syntax} className="rounded-xl border bg-card p-4">
                        <pre className="font-mono text-sm whitespace-pre-wrap">
                            {syntax}
                        </pre>
                        <p className="mt-1.5 text-xs text-muted-foreground">
                            {meaning}
                        </p>
                    </li>
                ))}
            </ul>
        </div>
    );
}
