import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { show as preview } from '@/routes/colour-group-previews';
import { destroy } from '@/routes/table-views';
import type { VaultSummary } from '@/types';

export type TableViewSettings = {
    name: string;
    query: string;
    columns: string[];
    sort_key: string | null;
    sort_desc: boolean;
};

type Props = {
    vault: VaultSummary;
    view: TableViewSettings & { id: number };
    /** Property keys used by the view's notes, to offer as columns. */
    keys: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSave: (settings: Partial<TableViewSettings>) => void;
};

/**
 * Name a view, choose its notes with a query and pick which properties become columns.
 */
export function TableViewDialog({
    vault,
    view,
    keys,
    open,
    onOpenChange,
    onSave,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="kite-no-drag sm:max-w-lg"
                data-test="view-dialog"
            >
                {open && (
                    <ViewForm
                        vault={vault}
                        view={view}
                        keys={keys}
                        onSave={onSave}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ViewForm({
    vault,
    view,
    keys,
    onSave,
}: Omit<Props, 'open' | 'onOpenChange'>) {
    const [name, setName] = useState(view.name);
    const [query, setQuery] = useState(view.query);
    const [columns, setColumns] = useState(view.columns);
    const [custom, setCustom] = useState('');
    const [count, setCount] = useState<{
        count: number | null;
        error: string | null;
    }>({ count: null, error: null });
    const offered = [...new Set([...columns, ...keys])];

    useEffect(() => {
        if (query.trim() === '') {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            requestJson<{ count: number | null; error: string | null }>(
                preview(vault.id, { query: { query } }),
                undefined,
                controller.signal,
            )
                .then(setCount)
                .catch((error: unknown) => {
                    if (!isAbortError(error)) {
                        setCount({ count: null, error: null });
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [vault.id, query]);

    const toggle = (key: string, on: boolean) =>
        setColumns((current) =>
            on ? [...current, key] : current.filter((item) => item !== key),
        );

    return (
        <form
            className="grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                onSave({
                    name: name.trim() || view.name,
                    query: query.trim(),
                    columns,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Edit view</DialogTitle>
                <DialogDescription>
                    A table of the notes your query matches, with properties as
                    columns.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-1.5">
                <Label htmlFor="view-name">Name</Label>
                <Input
                    id="view-name"
                    value={name}
                    onChange={(event) => setName(event.target.value)}
                />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="view-query">Notes</Label>
                <Input
                    id="view-query"
                    value={query}
                    placeholder="All notes, or e.g. folder:Projects status:active"
                    onChange={(event) => setQuery(event.target.value)}
                    className="font-mono text-sm"
                />
                <p
                    className="text-xs text-muted-foreground"
                    data-test="view-count"
                >
                    {query.trim() === ''
                        ? 'Every note in the vault.'
                        : (count.error ??
                          (count.count === null
                              ? '…'
                              : `${count.count} ${count.count === 1 ? 'note matches' : 'notes match'}.`))}
                </p>
            </div>

            <div className="grid gap-1.5">
                <Label>Columns</Label>
                <div className="grid max-h-48 gap-1 overflow-y-auto rounded-lg border p-2">
                    {offered.length === 0 && (
                        <p className="px-1 text-xs text-muted-foreground">
                            These notes have no properties yet. Add one below.
                        </p>
                    )}
                    {offered.map((key) => (
                        <label
                            key={key}
                            className="flex items-center gap-2 rounded-md px-1.5 py-1 text-sm hover:bg-accent"
                        >
                            <Checkbox
                                checked={columns.includes(key)}
                                onCheckedChange={(on) =>
                                    toggle(key, on === true)
                                }
                                aria-label={`Show ${key}`}
                            />
                            {key}
                        </label>
                    ))}
                </div>
                <div className="flex gap-2">
                    <Input
                        value={custom}
                        placeholder="Another property"
                        aria-label="Another property"
                        onChange={(event) => setCustom(event.target.value)}
                        className="h-8"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8"
                        disabled={
                            custom.trim() === '' ||
                            columns.includes(custom.trim())
                        }
                        onClick={() => {
                            toggle(custom.trim(), true);
                            setCustom('');
                        }}
                    >
                        <Plus />
                        Add
                    </Button>
                </div>
            </div>

            <DialogFooter className="sm:justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    className="text-destructive-foreground"
                    onClick={() =>
                        router.delete(
                            destroy({ vault: vault.id, tableView: view.id })
                                .url,
                        )
                    }
                    data-test="delete-view"
                >
                    <Trash2 />
                    Delete view
                </Button>
                <Button
                    type="submit"
                    disabled={count.error !== null && query.trim() !== ''}
                >
                    Save view
                </Button>
            </DialogFooter>
        </form>
    );
}
