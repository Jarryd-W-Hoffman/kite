import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useOpenExternalLink } from '@/hooks/use-open-external-link';
import { enhanceRichBlocks } from '@/lib/rich-blocks';
import { cn, prefersReducedMotion } from '@/lib/utils';
import { store } from '@/routes/notes';
import type { VaultSummary } from '@/types';

/**
 * Scroll the rendered preview to a heading anchor (ids are prefixed with "h-").
 */
export function scrollToHeading(slug: string): boolean {
    const heading = document.getElementById(`h-${decodeURIComponent(slug)}`);

    heading?.scrollIntoView({
        block: 'start',
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });

    return heading !== null;
}

type Props = {
    vault: VaultSummary;
    html: string;
    className?: string;
    testId?: string;
};

export function NotePreview({
    vault,
    html,
    className,
    testId = 'note-preview',
}: Props) {
    const container = useRef<HTMLDivElement>(null);
    const { resolvedAppearance } = useAppearance();
    const openExternalLink = useOpenExternalLink();

    useEffect(() => {
        if (container.current) {
            void enhanceRichBlocks(
                container.current,
                resolvedAppearance === 'dark',
            );
        }
    }, [html, resolvedAppearance]);

    const onClick = (event: React.MouseEvent<HTMLDivElement>) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const calloutTitle = event.target.closest('.callout-title');
        const callout = calloutTitle?.parentElement;

        // Foldable callouts ([!note]- / [!note]+) open and close from their title.
        if (callout?.dataset.fold) {
            callout.dataset.fold =
                callout.dataset.fold === 'closed' ? 'open' : 'closed';

            return;
        }

        const anchor = event.target.closest('a');

        if (!anchor) {
            return;
        }

        const href = anchor.getAttribute('href') ?? '';

        if (anchor.classList.contains('tag-link')) {
            event.preventDefault();
            router.visit(href);

            return;
        }

        if (anchor.classList.contains('heading-link')) {
            event.preventDefault();
            scrollToHeading(href.slice(1));

            return;
        }

        if (anchor.classList.contains('wiki-link')) {
            event.preventDefault();

            if (anchor.classList.contains('is-unresolved')) {
                router.post(store(vault.id).url, {
                    title: anchor.dataset.wikiTarget ?? anchor.textContent,
                });
            } else {
                router.visit(href);
            }

            return;
        }

        if (anchor.classList.contains('attachment-link')) {
            event.preventDefault();

            if (anchor.classList.contains('is-unresolved')) {
                toast.error(
                    `Attachment “${anchor.dataset.attachment ?? href}” was not found in this vault.`,
                );
            } else {
                window.open(href, '_blank', 'noopener');
            }

            return;
        }

        if (/^https?:\/\//i.test(href)) {
            event.preventDefault();
            openExternalLink(href);
        }
    };

    return (
        <div
            ref={container}
            className={cn('kite-prose px-12 pt-14 pb-24', className)}
            onClick={onClick}
            data-test={testId}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
