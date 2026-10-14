import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { uploadFile } from '@/lib/kite-http';
import { noteName } from '@/lib/note-tree';
import { show, store } from '@/routes/notes';
import { store as storeAttachment } from '@/routes/vault-attachments';
import type { TreeNote, VaultSummary } from '@/types';

/**
 * Find the note a wiki link target refers to: by name first, then by path suffix.
 */
export function findNote(
    tree: TreeNote[],
    target: string,
): TreeNote | undefined {
    const key = target.trim().toLowerCase().replace(/\.md$/, '');

    return (
        tree.find((item) => noteName(item.path).toLowerCase() === key) ??
        tree.find((item) =>
            item.path.toLowerCase().replace(/\.md$/, '').endsWith(key),
        )
    );
}

export function followWikiLink(
    vault: VaultSummary,
    tree: TreeNote[],
    target: string,
): void {
    const match = findNote(tree, target);

    if (match) {
        router.visit(show({ vault: vault.id, note: match.id }));
    } else {
        router.post(store(vault.id).url, { title: target });
    }
}

export async function uploadAttachments(
    vault: VaultSummary,
    files: File[],
): Promise<string[]> {
    const embeds: string[] = [];

    for (const file of files) {
        try {
            const result = await uploadFile<{ embed: string }>(
                storeAttachment(vault.id),
                file,
            );

            embeds.push(result.embed);
        } catch {
            toast.error(`Could not attach “${file.name}”.`);
        }
    }

    return embeds;
}
