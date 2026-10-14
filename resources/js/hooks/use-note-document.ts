import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { requestJson } from '@/lib/kite-http';
import { update } from '@/routes/notes';
import type { NoteDetail, NoteMeta, VaultSummary } from '@/types';

export type SaveState = 'saved' | 'unsaved' | 'saving' | 'error';

type Options = {
    autosaveMs: number;
    onSaved?: (meta: NoteMeta) => void;
};

/**
 * The editable state of one note: contents, autosave, save status and changes arriving from disk.
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
    const savedContents = useRef(note.contents);
    const latestContents = useRef(contents);
    const onSavedRef = useRef(onSaved);

    if (note.hash !== serverHash) {
        setServerHash(note.hash);

        if (contents === savedText) {
            setContentsState(note.contents);
            setSavedText(note.contents);
            setMeta(note);
        }
    }

    useEffect(() => {
        latestContents.current = contents;
        savedContents.current = savedText;
        onSavedRef.current = onSaved;
    });

    const isDirty = useCallback(
        () => latestContents.current !== savedContents.current,
        [],
    );

    const save = useCallback(async () => {
        const pending = latestContents.current;

        if (pending === savedContents.current) {
            return;
        }

        setSaveState('saving');

        try {
            const saved = await requestJson<NoteMeta>(
                update({ vault: vault.id, note: note.id }),
                { contents: pending },
            );

            savedContents.current = pending;
            setSavedText(pending);
            setMeta(saved);
            setSaveState(
                latestContents.current === pending ? 'saved' : 'unsaved',
            );
            onSavedRef.current?.(saved);
        } catch {
            setSaveState('error');
            toast.error('Could not save the note.');
        }
    }, [vault.id, note.id]);

    useEffect(() => {
        if (contents === savedText) {
            return;
        }

        const timeout = window.setTimeout(() => void save(), autosaveMs);

        return () => window.clearTimeout(timeout);
    }, [contents, savedText, save, autosaveMs]);

    useEffect(() => () => void save(), [save]);

    const setContents = useCallback((value: string) => {
        setContentsState(value);
        setSaveState('unsaved');
    }, []);

    return { contents, setContents, meta, saveState, save, isDirty };
}
