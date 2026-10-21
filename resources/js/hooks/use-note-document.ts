import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { requestJson } from '@/lib/kite-http';
import { update } from '@/routes/notes';
import type { NoteDetail, NoteMeta, VaultSummary } from '@/types';

export type SaveState = 'saved' | 'unsaved' | 'saving' | 'error' | 'conflict';

type Options = {
    autosaveMs: number;
    onSaved?: (meta: NoteMeta) => void;
};

/**
 * The editable state of one note: contents, autosave, save status and changes arriving from disk.
 *
 * Saves carry the hash of the file this copy was loaded or last saved as, and one runs at a
 * time. If the note changed on disk while this copy has unsaved edits (in another app, a sync
 * or the other pane), nothing is overwritten: autosave pauses and `conflict` holds the note as
 * it is on disk, until `keepMine()` or `takeDisk()` settles it.
 */
export function useNoteDocument(
    vault: VaultSummary,
    note: NoteDetail,
    { autosaveMs, onSaved }: Options,
) {
    const [contents, setContentsState] = useState(note.contents);
    const [meta, setMeta] = useState<NoteMeta>(note);
    const [saveState, setSaveState] = useState<SaveState>('saved');
    const [savedText, setSavedText] = useState(note.contents);
    const [serverHash, setServerHash] = useState(note.hash);
    const [baseHash, setBaseHash] = useState(note.hash);
    const [sending, setSending] = useState<string | null>(null);
    const [conflict, setConflict] = useState<NoteDetail | null>(null);
    const savedContents = useRef(note.contents);
    const latestContents = useRef(contents);
    const base = useRef(note.hash);
    const conflicted = useRef(false);
    const saving = useRef<Promise<void> | null>(null);
    const onSavedRef = useRef(onSaved);

    if (note.hash !== serverHash) {
        setServerHash(note.hash);

        // Ignore a reload that only shows this copy's own save (or the same text).
        const ours =
            note.hash === baseHash ||
            note.contents === sending ||
            note.contents === contents;

        if (!ours && contents === savedText) {
            setContentsState(note.contents);
            setSavedText(note.contents);
            setBaseHash(note.hash);
            setMeta(note);
        } else if (!ours) {
            setConflict(note);
            setSaveState('conflict');
        }
    }

    useEffect(() => {
        latestContents.current = contents;
        savedContents.current = savedText;
        base.current = baseHash;
        conflicted.current = conflict !== null;
        onSavedRef.current = onSaved;
    });

    const isDirty = useCallback(
        () => latestContents.current !== savedContents.current,
        [],
    );

    /** Save what's in the editor. Resolves to whether it is saved now. */
    const save = useCallback(async (): Promise<boolean> => {
        while (saving.current) {
            await saving.current;
        }

        const pending = latestContents.current;

        if (conflicted.current) {
            return false;
        }

        if (pending === savedContents.current) {
            return true;
        }

        let saved = false;

        setSaveState('saving');
        setSending(pending);

        saving.current = requestJson<NoteMeta>(
            update({ vault: vault.id, note: note.id }),
            { contents: pending, base_hash: base.current },
        )
            .then((response) => {
                saved = true;
                savedContents.current = pending;
                base.current = response.hash;
                setSavedText(pending);
                setBaseHash(response.hash);
                setMeta(response);
                setSaveState(
                    latestContents.current === pending ? 'saved' : 'unsaved',
                );
                onSavedRef.current?.(response);
            })
            .catch((error: unknown) => {
                const response = (
                    error as { response?: { status?: number; data?: unknown } }
                ).response;

                if (response?.status === 409) {
                    const body = (
                        typeof response.data === 'string'
                            ? JSON.parse(response.data)
                            : response.data
                    ) as { note: NoteDetail };

                    conflicted.current = true;
                    setConflict(body.note);
                    setSaveState('conflict');

                    return;
                }

                setSaveState('error');
                toast.error('Could not save the note.');
            })
            .finally(() => {
                saving.current = null;
                setSending(null);
            });

        await saving.current;

        return saved;
    }, [vault.id, note.id]);

    useEffect(() => {
        if (contents === savedText || conflict) {
            return;
        }

        const timeout = window.setTimeout(() => void save(), autosaveMs);

        return () => window.clearTimeout(timeout);
    }, [contents, savedText, conflict, save, autosaveMs]);

    useEffect(() => () => void save(), [save]);

    const setContents = useCallback((value: string) => {
        setContentsState(value);
        setSaveState((state) => (state === 'conflict' ? state : 'unsaved'));
    }, []);

    /** Take the server's copy outright, e.g. after restoring an old version. */
    const adopt = useCallback((fresh: NoteDetail) => {
        setServerHash(fresh.hash);
        setBaseHash(fresh.hash);
        base.current = fresh.hash;
        setConflict(null);
        conflicted.current = false;
        setContentsState(fresh.contents);
        setSavedText(fresh.contents);
        savedContents.current = fresh.contents;
        latestContents.current = fresh.contents;
        setMeta(fresh);
        setSaveState('saved');
    }, []);

    /** Settle a conflict by saving this copy over the version on disk. */
    const keepMine = useCallback(() => {
        if (!conflict) {
            return;
        }

        base.current = conflict.hash;
        conflicted.current = false;
        setBaseHash(conflict.hash);
        setConflict(null);
        setSaveState('unsaved');
        void save();
    }, [conflict, save]);

    /** Replace the text and save it straight away, such as the result of a merge. */
    const saveText = useCallback(
        (text: string): Promise<boolean> => {
            latestContents.current = text;
            setContentsState(text);
            setSaveState('unsaved');

            return save();
        },
        [save],
    );

    /** Settle a conflict by saving a merge of this copy and the version on disk over the latter. */
    const keepMerged = useCallback(
        (text: string) => {
            if (!conflict) {
                return;
            }

            base.current = conflict.hash;
            conflicted.current = false;
            setBaseHash(conflict.hash);
            setConflict(null);
            void saveText(text);
        },
        [conflict, saveText],
    );

    /** Settle a conflict by dropping this copy's unsaved edits for the version on disk. */
    const takeDisk = useCallback(() => {
        if (conflict) {
            adopt(conflict);
        }
    }, [conflict, adopt]);

    return {
        contents,
        setContents,
        meta,
        saveState,
        save,
        isDirty,
        adopt,
        /** The text as last loaded or saved: where this copy and the one on disk parted. */
        savedText,
        saveText,
        conflict,
        keepMine,
        keepMerged,
        takeDisk,
    };
}
