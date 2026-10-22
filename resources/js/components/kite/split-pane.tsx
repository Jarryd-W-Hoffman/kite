import { Link, usePage } from '@inertiajs/react';
import { Eye, Maximize2, PencilLine, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { NoteEditor } from '@/components/kite/note-editor';
import { NotePreview } from '@/components/kite/note-preview';
import { SaveConflictBanner } from '@/components/kite/save-conflict';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useNoteDocument } from '@/hooks/use-note-document';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { followWikiLink, uploadAttachments } from '@/lib/note-actions';
import { show as showNotePane } from '@/routes/note-panes';
import { show } from '@/routes/notes';
import type { NoteDetail, TreeNote, VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    noteId: number;
    tree: TreeNote[];
    onClose: () => void;
};

/**
 * A second, independently editable note shown beside the current one.
 */
export function SplitPane({ vault, noteId, tree, onClose }: Props) {
    const [note, setNote] = useState<NoteDetail | null>(null);
    const [loadedId, setLoadedId] = useState<number | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        requestJson<{ note: NoteDetail }>(
            showNotePane({ vault: vault.id, note: noteId }),
            undefined,
            controller.signal,
        )
            .then((response) => {
                setNote(response.note);
                setLoadedId(noteId);
            })
            .catch((error: unknown) => {
                if (!isAbortError(error)) {
                    onClose();
                }
            });

        return () => controller.abort();
    }, [vault.id, noteId, onClose]);

    return (
        <section
            aria-label="Split pane"
            data-test="split-pane"
            className="flex min-w-0 flex-1 flex-col border-l"
        >
            {note && loadedId === noteId ? (
                <SplitPaneDocument
                    key={note.id}
                    vault={vault}
                    note={note}
                    tree={tree}
                    onClose={onClose}
                />
            ) : (
                <div className="space-y-3 p-6" aria-busy="true">
                    <Skeleton className="h-5 w-1/3 animate-pulse" />
                    <Skeleton className="h-4 w-full animate-pulse" />
                    <Skeleton className="h-4 w-2/3 animate-pulse" />
                </div>
            )}
        </section>
    );
}

function SplitPaneDocument({
    vault,
    note,
    tree,
    onClose,
}: Omit<Props, 'noteId'> & { note: NoteDetail }) {
    const { editor } = usePage().props.kite;
    const [previewing, setPreviewing] = useState(false);
    const {
        contents,
        setContents,
        meta,
        saveState,
        save,
        savedText,
        conflict,
        keepMine,
        keepMerged,
        takeDisk,
    } = useNoteDocument(vault, note, { autosaveMs: editor.autosave_ms });

    return (
        <>
            <header className="flex h-10 shrink-0 items-center gap-2 border-b px-3 text-sm">
                <span className="min-w-0 flex-1 truncate font-medium">
                    {meta.title}
                </span>
                <span className="text-xs text-muted-foreground">
                    {saveState === 'saved'
                        ? 'Saved'
                        : saveState === 'conflict'
                          ? 'Changed on disk'
                          : 'Edited'}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    aria-label={previewing ? 'Edit' : 'Preview'}
                    onClick={() => setPreviewing((value) => !value)}
                >
                    {previewing ? <PencilLine /> : <Eye />}
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    aria-label="Open as main note"
                    asChild
                >
                    <Link href={show({ vault: vault.id, note: note.id })}>
                        <Maximize2 />
                    </Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    aria-label="Close split pane"
                    data-test="close-split"
                    onClick={onClose}
                >
                    <X />
                </Button>
            </header>
            {conflict && (
                <SaveConflictBanner
                    disk={conflict.contents}
                    mine={contents}
                    base={savedText}
                    onKeepMine={keepMine}
                    onTakeDisk={takeDisk}
                    onMerge={keepMerged}
                    compact
                />
            )}
            <div className="min-h-0 flex-1 overflow-y-auto">
                {previewing ? (
                    <NotePreview
                        vault={vault}
                        html={meta.html}
                        testId="split-preview"
                    />
                ) : (
                    <NoteEditor
                        vault={vault}
                        value={contents}
                        onChange={setContents}
                        onSave={() => void save()}
                        onUpload={(files) => uploadAttachments(vault, files)}
                        onFollowLink={(target) =>
                            followWikiLink(vault, tree, target)
                        }
                        notes={tree}
                        testId="split-editor"
                    />
                )}
            </div>
        </>
    );
}
