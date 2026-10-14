import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { EditorComment } from '@/components/kite/note-editor';
import { captureAnchor, findAnchor } from '@/lib/comment-anchors';
import type { AnchorRange, CommentAnchor } from '@/lib/comment-anchors';
import type { CommentRanges } from '@/lib/comment-highlights';
import { requestJson } from '@/lib/kite-http';
import { destroy, store, update } from '@/routes/note-comments';
import type { NoteComment, VaultSummary } from '@/types';

/** The comment being written, highlighted like the others until it is saved. */
export const DRAFT_ID = 'draft';

/** How long the text must stay still before moved anchors are saved. */
const ANCHOR_SAVE_DELAY = 1000;

type Options = {
    vault: VaultSummary;
    noteId: number;
    initial: NoteComment[];
    /** The note's current text. */
    contents: string;
    /** Whether an editor is tracking positions; otherwise they are found in `contents`. */
    editing: boolean;
};

/**
 * A note's comments: the list, the draft being written, which one is selected and where each
 * one currently sits. Positions that moved because the note was edited are saved back, so the
 * comment is found again next time the note is opened.
 */
export function useNoteComments({
    vault,
    noteId,
    initial,
    contents,
    editing,
}: Options) {
    const [comments, setComments] = useState(initial);
    const [draft, setDraft] = useState<CommentAnchor | null>(null);
    const [activeId, setActiveId] = useState<string | null>(null);
    const [editorRanges, setEditorRanges] = useState<CommentRanges | null>(
        null,
    );
    const saving = useRef(new Set<string>());

    const editorComments = useMemo<EditorComment[]>(
        () => [
            ...comments.map((comment) => ({
                id: comment.id,
                quote: comment.quote,
                prefix: comment.prefix,
                suffix: comment.suffix,
                resolved: comment.resolved,
            })),
            ...(draft ? [{ ...draft, id: DRAFT_ID, resolved: false }] : []),
        ],
        [comments, draft],
    );

    const textRanges = useMemo(() => {
        const ranges = new Map<string, AnchorRange>();

        if (!editing) {
            for (const comment of editorComments) {
                const range = findAnchor(contents, comment);

                if (range) {
                    ranges.set(comment.id, range);
                }
            }
        }

        return ranges;
    }, [editing, editorComments, contents]);

    // Until the editor reports in, nothing is known to be orphaned.
    const ranges: CommentRanges | null = editing ? editorRanges : textRanges;

    useEffect(() => {
        if (!editing || !editorRanges) {
            return;
        }

        const timeout = window.setTimeout(() => {
            for (const comment of comments) {
                const range = editorRanges.get(comment.id);

                if (!range || saving.current.has(comment.id)) {
                    continue;
                }

                const anchor = captureAnchor(contents, range.from, range.to);

                if (
                    anchor.quote !== comment.quote ||
                    anchor.prefix !== comment.prefix ||
                    anchor.suffix !== comment.suffix
                ) {
                    saving.current.add(comment.id);
                    void send(comment.id, anchor, true).finally(() =>
                        saving.current.delete(comment.id),
                    );
                }
            }
        }, ANCHOR_SAVE_DELAY);

        return () => window.clearTimeout(timeout);
        // `send` only closes over stable values.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing, editorRanges, contents, comments]);

    const replace = (comment: NoteComment) =>
        setComments((current) =>
            current.map((item) => (item.id === comment.id ? comment : item)),
        );

    async function send(
        id: string,
        changes: Partial<CommentAnchor & { body: string; resolved: boolean }>,
        quietly = false,
    ): Promise<void> {
        try {
            replace(
                await requestJson<NoteComment>(
                    update({ vault: vault.id, note: noteId, comment: id }),
                    changes,
                ),
            );
        } catch {
            if (!quietly) {
                toast.error('Could not update the comment.');
            }
        }
    }

    const add = async (body: string): Promise<boolean> => {
        if (!draft) {
            return false;
        }

        try {
            const comment = await requestJson<NoteComment>(
                store({ vault: vault.id, note: noteId }),
                { ...draft, body },
            );

            setComments((current) => [...current, comment]);
            setDraft(null);
            setActiveId(comment.id);

            return true;
        } catch {
            toast.error('Could not save the comment.');

            return false;
        }
    };

    const remove = async (id: string): Promise<void> => {
        try {
            await requestJson(
                destroy({ vault: vault.id, note: noteId, comment: id }),
            );
            setComments((current) => current.filter((item) => item.id !== id));
            setActiveId((current) => (current === id ? null : current));
        } catch {
            toast.error('Could not delete the comment.');
        }
    };

    return {
        comments,
        editorComments,
        ranges,
        draft,
        activeId,
        setActiveId,
        startDraft: (anchor: CommentAnchor) => {
            setDraft(anchor);
            setActiveId(DRAFT_ID);
        },
        cancelDraft: () => {
            setDraft(null);
            setActiveId(null);
        },
        setEditorRanges,
        add,
        edit: (id: string, body: string) => send(id, { body }),
        setResolved: (id: string, resolved: boolean) => send(id, { resolved }),
        remove,
    };
}

export type NoteComments = ReturnType<typeof useNoteComments>;
