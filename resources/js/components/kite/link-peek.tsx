import { LockKeyhole } from 'lucide-react';
import { type RefObject, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Skeleton } from '@/components/ui/skeleton';
import { useAppearance } from '@/hooks/use-appearance';
import { requestJson } from '@/lib/kite-http';
import { enhanceRichBlocks } from '@/lib/rich-blocks';
import { show } from '@/routes/note-peeks';
import type { VaultSummary } from '@/types';

/** Which note, and which heading or block of it (or none), a link goes to. */
export type PeekTarget = { noteId: number; section: string };

type Peek = {
    title: string;
    path: string;
    locked: boolean;
    html: string;
    truncated: boolean;
};

type Props = {
    vault: VaultSummary;
    containerRef: RefObject<HTMLElement | null>;
    /** The link an element under the pointer is (or is in), if it goes to a note. */
    resolve: (element: Element) => PeekTarget | null;
    /** Only preview while Ctrl or ⌘ is held, so writing isn't interrupted. */
    requireModifier?: boolean;
};

const SHOW_AFTER_MS = 400;
const HIDE_AFTER_MS = 250;
const WIDTH = 420;
const HEIGHT = 320;

const cache = new Map<string, Promise<Peek>>();

function load(vault: VaultSummary, target: PeekTarget): Promise<Peek> {
    const key = `${vault.id}:${target.noteId}:${target.section}`;
    const cached = cache.get(key);

    if (cached) {
        return cached;
    }

    const request = requestJson<Peek>(
        show(
            { vault: vault.id, note: target.noteId },
            { query: target.section === '' ? {} : { section: target.section } },
        ),
    ).catch((error: unknown) => {
        cache.delete(key);
        throw error;
    });

    cache.set(key, request);

    // Keep previews fresh enough: a note may change while the window stays open.
    window.setTimeout(() => cache.delete(key), 30_000);

    return request;
}

/**
 * A preview of the note a link goes to, shown while the pointer rests on the link.
 */
export function LinkPeek({
    vault,
    containerRef,
    resolve,
    requireModifier = false,
}: Props) {
    const [anchor, setAnchor] = useState<DOMRect | null>(null);
    const [peek, setPeek] = useState<Peek | null>(null);
    const showTimer = useRef<number | undefined>(undefined);
    const hideTimer = useRef<number | undefined>(undefined);
    const resolveRef = useRef(resolve);
    const shown = useRef<string | null>(null);

    useEffect(() => {
        resolveRef.current = resolve;
    });

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const hide = () => {
            window.clearTimeout(showTimer.current);
            shown.current = null;
            setAnchor(null);
            setPeek(null);
        };

        const onOver = (event: MouseEvent) => {
            const target =
                event.target instanceof Element
                    ? resolveRef.current(event.target)
                    : null;

            if (
                !target ||
                (requireModifier && !(event.metaKey || event.ctrlKey))
            ) {
                return;
            }

            window.clearTimeout(hideTimer.current);
            window.clearTimeout(showTimer.current);

            const key = `${target.noteId}:${target.section}`;
            const element = event.target as Element;

            if (shown.current === key) {
                return;
            }

            showTimer.current = window.setTimeout(() => {
                shown.current = key;
                setPeek(null);
                setAnchor(element.getBoundingClientRect());
                load(vault, target)
                    .then((result) => {
                        if (shown.current === key) {
                            setPeek(result);
                        }
                    })
                    .catch(hide);
            }, SHOW_AFTER_MS);
        };

        const onOut = (event: MouseEvent) => {
            if (
                event.target instanceof Element &&
                resolveRef.current(event.target)
            ) {
                window.clearTimeout(showTimer.current);
                hideTimer.current = window.setTimeout(hide, HIDE_AFTER_MS);
            }
        };

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                hide();
            }
        };

        container.addEventListener('mouseover', onOver);
        container.addEventListener('mouseout', onOut);
        window.addEventListener('keydown', onKey);
        window.addEventListener('scroll', hide, true);

        return () => {
            container.removeEventListener('mouseover', onOver);
            container.removeEventListener('mouseout', onOut);
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('scroll', hide, true);
            window.clearTimeout(showTimer.current);
            window.clearTimeout(hideTimer.current);
        };
    }, [containerRef, vault, requireModifier]);

    if (anchor === null) {
        return null;
    }

    const below = anchor.bottom + 6 + HEIGHT < window.innerHeight;
    const left = Math.max(
        8,
        Math.min(anchor.left, window.innerWidth - WIDTH - 8),
    );

    return createPortal(
        <div
            role="tooltip"
            className="fixed z-50 flex flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-kite-lg"
            style={{
                left,
                width: WIDTH,
                maxHeight: HEIGHT,
                ...(below
                    ? { top: anchor.bottom + 6 }
                    : { bottom: window.innerHeight - anchor.top + 6 }),
            }}
            onMouseEnter={() => window.clearTimeout(hideTimer.current)}
            onMouseLeave={() => {
                hideTimer.current = window.setTimeout(() => {
                    shown.current = null;
                    setAnchor(null);
                    setPeek(null);
                }, HIDE_AFTER_MS);
            }}
            data-test="link-peek"
        >
            {peek === null ? (
                <div className="space-y-2 p-4" aria-busy="true">
                    <Skeleton className="h-4 w-1/2 animate-pulse" />
                    <Skeleton className="h-3 w-full animate-pulse" />
                    <Skeleton className="h-3 w-4/5 animate-pulse" />
                </div>
            ) : (
                <PeekContent peek={peek} />
            )}
        </div>,
        document.body,
    );
}

function PeekContent({ peek }: { peek: Peek }) {
    const body = useRef<HTMLDivElement>(null);
    const { resolvedAppearance } = useAppearance();

    useEffect(() => {
        if (body.current) {
            void enhanceRichBlocks(body.current, resolvedAppearance === 'dark');
        }
    }, [peek.html, resolvedAppearance]);

    return (
        <>
            <div className="shrink-0 border-b px-4 py-2">
                <div className="truncate text-sm font-semibold">
                    {peek.title}
                </div>
                <div className="truncate text-xs text-muted-foreground">
                    {peek.path}
                </div>
            </div>
            {peek.locked ? (
                <p className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
                    <LockKeyhole className="size-4" />
                    This note is locked.
                </p>
            ) : (
                <div className="min-h-0 flex-1 overflow-y-auto">
                    <div
                        ref={body}
                        className="kite-prose kite-peek px-4 py-3 text-sm"
                        data-test="link-peek-body"
                        dangerouslySetInnerHTML={{ __html: peek.html }}
                    />
                    {peek.truncated && (
                        <p className="px-4 pb-3 text-xs text-muted-foreground">
                            Open the note to read the rest.
                        </p>
                    )}
                </div>
            )}
        </>
    );
}
