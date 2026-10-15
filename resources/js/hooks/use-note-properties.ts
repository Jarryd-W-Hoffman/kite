import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { Properties } from '@/components/kite/properties-editor';
import { requestJson } from '@/lib/kite-http';
import { update as updateProperties } from '@/routes/note-properties';
import type { VaultSummary } from '@/types';

/**
 * A note's properties (its frontmatter) as edited in the properties panel. Each change is
 * written into the note's text on the server; changes run one at a time on the newest text, so
 * quick edits never undo each other.
 */
export function useNoteProperties(
    vault: VaultSummary,
    noteId: number,
    frontmatter: Properties,
    contents: string,
    setContents: (contents: string) => void,
) {
    const [properties, setProperties] = useState<Properties>(frontmatter);
    const [savedFrontmatter, setSavedFrontmatter] = useState(frontmatter);
    const latestContents = useRef(contents);
    const queue = useRef<Promise<void>>(Promise.resolve());

    // A save (or a change on disk) brings the parsed frontmatter back from the server.
    if (frontmatter !== savedFrontmatter) {
        setSavedFrontmatter(frontmatter);
        setProperties(frontmatter);
    }

    useEffect(() => {
        latestContents.current = contents;
    }, [contents]);

    const changeProperties = (next: Properties) => {
        setProperties(next);
        queue.current = queue.current.then(async () => {
            try {
                const response = await requestJson<{
                    contents: string;
                    properties: Properties;
                }>(updateProperties({ vault: vault.id, note: noteId }), {
                    contents: latestContents.current,
                    properties: next,
                });

                latestContents.current = response.contents;
                setContents(response.contents);
                setProperties(response.properties);
            } catch {
                toast.error('Could not update the properties.');
            }
        });
    };

    return { properties, changeProperties };
}
