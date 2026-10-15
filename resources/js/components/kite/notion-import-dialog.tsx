import { useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as storeNotionImport } from '@/routes/notion-imports';
import type { VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * Bring a Notion workspace in: pick the zip from Notion's "Markdown & CSV" export and the folder
 * its pages go into.
 */
export function NotionImportDialog({ vault, open, onOpenChange }: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-md"
                data-test="notion-import-dialog"
            >
                {open && (
                    <ImportForm
                        vault={vault}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ImportForm({
    vault,
    onDone,
}: {
    vault: VaultSummary;
    onDone: () => void;
}) {
    const form = useForm<{ file: File | null; folder: string }>({
        file: null,
        folder: 'Notion',
    });

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(storeNotionImport(vault.id).url, {
                    forceFormData: true,
                    onSuccess: onDone,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Import from Notion</DialogTitle>
                <DialogDescription>
                    In Notion, choose <strong>Settings → Export</strong> (or a
                    page's <strong>Export</strong>) as{' '}
                    <strong>Markdown &amp; CSV</strong>, then pick the zip here.
                    Pages keep their links and images, and databases become
                    table views.
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-1.5">
                <Label htmlFor="notion-file">Export zip</Label>
                <Input
                    id="notion-file"
                    name="file"
                    type="file"
                    accept=".zip,application/zip"
                    onChange={(event) =>
                        form.setData('file', event.target.files?.[0] ?? null)
                    }
                    className="text-sm file:mr-3"
                />
                {form.errors.file && (
                    <p className="text-sm text-destructive">
                        {form.errors.file}
                    </p>
                )}
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="notion-folder">Into folder</Label>
                <Input
                    id="notion-folder"
                    name="folder"
                    value={form.data.folder}
                    onChange={(event) =>
                        form.setData('folder', event.target.value)
                    }
                />
                <p className="text-xs text-muted-foreground">
                    A new folder is used if this one already exists.
                </p>
            </div>
            {form.progress && (
                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full bg-primary transition-[width]"
                        style={{ width: `${form.progress.percentage ?? 0}%` }}
                    />
                </div>
            )}
            <DialogFooter>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={form.processing || form.data.file === null}
                >
                    {form.processing && <Spinner />}
                    Import
                </Button>
            </DialogFooter>
        </form>
    );
}
