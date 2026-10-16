import { Head, router } from '@inertiajs/react';
import { FileWarning, Presentation, RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CanvasBoard } from '@/components/kite/canvas/canvas-board';
import { CommandPalette } from '@/components/kite/command-palette';
import { EmptyState } from '@/components/kite/empty-state';
import { WorkspaceSidebar } from '@/components/kite/workspace-sidebar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCanvasDocument } from '@/hooks/use-canvas-document';
import type { CanvasSaveState } from '@/hooks/use-canvas-document';
import { useVaultSync } from '@/hooks/use-vault-sync';
import type { CanvasEdge, CanvasFile, CanvasNode } from '@/lib/canvas';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/canvases';
import type { WorkspaceProps } from '@/types';

type Props = WorkspaceProps & {
    canvas: {
        path: string;
        name: string;
        hash: string | null;
        readable: boolean;
        nodes: CanvasNode[];
        edges: CanvasEdge[];
    };
    files: Record<string, CanvasFile>;
    texts: Record<string, string>;
};

const SAVE_LABELS: Record<CanvasSaveState, string> = {
    saved: 'Saved',
    unsaved: 'Edited',
    saving: 'Saving…',
    error: 'Not saved',
    conflict: 'Changed on disk',
};

export default function CanvasShow(props: Props) {
    const { vault, vaults, tree, folders, tags, indexing, watching, canvas } =
        props;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const doc = useCanvasDocument(
        vault,
        canvas.path,
        { nodes: canvas.nodes, edges: canvas.edges },
        canvas.hash,
    );

    // Changes elsewhere refresh the sidebar; the canvas itself is never swapped out from under edits.
    useVaultSync(
        vault,
        () =>
            router.reload({
                only: ['tree', 'folders', 'tags', 'colour_groups', 'canvases'],
            }),
        watching,
    );

    return (
        <>
            <Head title={`${canvas.name} · ${vault.name}`} />

            <div className="flex h-svh overflow-hidden bg-background">
                <WorkspaceSidebar
                    vault={vault}
                    vaults={vaults}
                    tree={tree}
                    folders={folders}
                    colourGroups={props.colour_groups}
                    tags={tags}
                    activeNoteId={null}
                    activeCanvasPath={canvas.path}
                    indexing={indexing}
                    onSearch={() => setPaletteOpen(true)}
                />

                <main className="relative flex min-w-0 flex-1 flex-col">
                    <header className="kite-drag flex h-12 shrink-0 items-center gap-2 border-b border-border/70 px-4">
                        <h1 className="flex min-w-0 items-center gap-2 text-[13.5px] font-semibold">
                            <Presentation className="size-4 shrink-0 text-primary" />
                            <span className="truncate" data-test="canvas-name">
                                {canvas.name}
                            </span>
                        </h1>
                        {canvas.readable && (
                            <span
                                className={cn(
                                    'shrink-0 text-xs text-muted-foreground',
                                    (doc.saveState === 'error' ||
                                        doc.saveState === 'conflict') &&
                                        'text-destructive',
                                )}
                                aria-live="polite"
                                data-test="canvas-save-state"
                            >
                                {SAVE_LABELS[doc.saveState]}
                            </span>
                        )}
                        <div className="kite-no-drag ml-auto flex items-center gap-1">
                            {doc.saveState === 'conflict' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.reload()}
                                >
                                    <RotateCw />
                                    Reload
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label="Delete canvas"
                                data-test="delete-canvas"
                                onClick={() => setDeleting(true)}
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </header>

                    {canvas.readable ? (
                        <CanvasBoard
                            vault={vault}
                            tree={tree}
                            doc={doc}
                            initialFiles={props.files}
                            initialTexts={props.texts}
                        />
                    ) : (
                        <div className="flex flex-1 items-center justify-center p-8">
                            <EmptyState
                                icon={FileWarning}
                                title="This canvas can’t be read"
                                description={
                                    <>
                                        <code className="font-mono">
                                            {canvas.path}
                                        </code>{' '}
                                        isn’t a JSON Canvas file. Kite won’t
                                        change it; fix it in a text editor, or
                                        delete it.
                                    </>
                                }
                            />
                        </div>
                    )}
                </main>
            </div>

            <CommandPalette
                vault={vault}
                open={paletteOpen}
                onOpenChange={setPaletteOpen}
            />

            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent
                    className="kite-no-drag sm:max-w-md"
                    data-test="delete-canvas-dialog"
                >
                    <DialogHeader>
                        <DialogTitle>Delete “{canvas.name}”?</DialogTitle>
                        <DialogDescription>
                            The canvas file goes to the trash. Notes and files
                            on it aren’t touched.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(
                                    destroy({
                                        vault: vault.id,
                                        path: canvas.path,
                                    }).url,
                                )
                            }
                        >
                            Delete canvas
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
