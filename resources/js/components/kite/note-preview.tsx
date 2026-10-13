import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';
import { store } from '@/routes/notes';
import type { VaultSummary } from '@/types';

/**
 * Scroll the rendered preview to a heading anchor (ids are prefixed with "h-").
 */
export function scrollToHeading(slug: string): boolean {
    const heading = document.getElementById(`h-${decodeURIComponent(slug)}`);

    heading?.scrollIntoView({ block: 'start', behavior: 'smooth' });

    return heading !== null;
}

type Props = {
    vault: VaultSummary;
    html: string;
    className?: string;
};

export function NotePreview({ vault, html, className }: Props) {
    const onClick = (event: React.MouseEvent<HTMLDivElement>) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const anchor = event.target.closest('a');

        if (!anchor) {
            return;
        }

        const href = anchor.getAttribute('href') ?? '';

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
            window.open(href, '_blank', 'noopener,noreferrer');
        }
    };

    return (
        <div
            className={cn('kite-prose px-10 py-8', className)}
            onClick={onClick}
            data-test="note-preview"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
