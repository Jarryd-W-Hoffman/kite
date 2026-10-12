import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { store } from '@/routes/notes';
import type { VaultSummary } from '@/types';

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
