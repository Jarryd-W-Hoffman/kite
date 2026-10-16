import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { Properties } from '@/components/kite/properties-editor';
import { requestJson } from '@/lib/kite-http';
import { update as updateProperties } from '@/routes/note-properties';
import type { VaultSummary } from '@/types';

/**
 * A note's properties (its frontmatter) as edited in the properties panel. Each change is
 * written into the note's text on the server; changes run one at a time on the newest text, so
 * quick edits never undo each other. While changes are on their way, only the last one's reply
 * updates what's shown: an earlier reply would put back values a later change has replaced.
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
    const [pending, setPending] = useState(0);
    const latestContents = useRef(contents);
    const latestChange = useRef(0);
    const queue = useRef<Promise<void>>(Promise.resolve());

    // A save (or a change on disk) brings the parsed frontmatter back from the server; changes
    // still on their way are newer, so they win.
    if (frontmatter !== savedFrontmatter) {
        setSavedFrontmatter(frontmatter);

        if (pending === 0) {
            setProperties(frontmatter);
        }
    }

    useEffect(() => {
        latestContents.current = contents;
    }, [contents]);

    const changeProperties = (next: Properties) => {
        const change = ++latestChange.current;

        setProperties(next);
        setPending((count) => count + 1);
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

                if (change === latestChange.current) {
                    setProperties(response.properties);
                }
            } catch {
                toast.error('Could not update the properties.');
            } finally {
                setPending((count) => count - 1);
            }
        });
    };

    return { properties, changeProperties };
}
