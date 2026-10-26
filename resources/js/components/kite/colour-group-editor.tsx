import { router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Check, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import {
    QueryInput,
    keepOpenWhileSuggesting,
} from '@/components/kite/query-input';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { GROUP_COLOURS, groupColourVariable } from '@/lib/colour-groups';
import type { ColourGroup, GroupColour } from '@/lib/colour-groups';
import { isAbortError, requestJson } from '@/lib/kite-http';
import { update as updateOrder } from '@/routes/colour-group-order';
import { show as preview } from '@/routes/colour-group-previews';
import { destroy, store, update } from '@/routes/colour-groups';
import type { VaultSummary } from '@/types';

type Props = {
    vault: VaultSummary;
    groups: ColourGroup[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Page props to refresh after a change, so colours update without a full reload. */
    reload: string[];
};

/**
 * Side panel for a vault's colour groups: edit queries with a live match count, pick colours and
 * set the order that decides which group a note takes.
 */
export function ColourGroupEditor({
    vault,
    groups,
    open,
    onOpenChange,
    reload,
}: Props) {
    const options = { only: reload, preserveScroll: true, preserveState: true };

    const move = (index: number, offset: number) => {
        const ids = groups.map((group) => group.id);
        const [moved] = ids.splice(index, 1);
        ids.splice(index + offset, 0, moved);

        router.put(updateOrder(vault.id).url, { ids }, options);
    };

    return (
        // Non-modal, so the graph stays visible and its colours update live while editing.
        <Sheet open={open} onOpenChange={onOpenChange} modal={false}>
            <SheetContent
                className="kite-no-drag w-full gap-0 sm:max-w-md"
                data-test="colour-group-editor"
                onInteractOutside={(event) => event.preventDefault()}
                onEscapeKeyDown={keepOpenWhileSuggesting}
            >
                <SheetHeader className="border-b">
                    <SheetTitle>Colour groups</SheetTitle>
                    <SheetDescription>
                        Notes take the colour of the first group they match, top
                        to bottom.
                    </SheetDescription>
                </SheetHeader>

                <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    {groups.map((group, index) => (
                        <GroupForm
                            key={group.id}
                            vault={vault}
                            group={group}
                            options={options}
                            onMoveUp={
                                index > 0 ? () => move(index, -1) : undefined
                            }
                            onMoveDown={
                                index < groups.length - 1
                                    ? () => move(index, 1)
                                    : undefined
                            }
                        />
                    ))}

                    <GroupForm
                        key={`new-${groups.length}`}
                        vault={vault}
                        options={options}
                        defaultColour={
                            GROUP_COLOURS.find(
                                (colour) =>
                                    !groups.some(
                                        (group) => group.colour === colour,
                                    ),
                            ) ?? GROUP_COLOURS[0]
                        }
                    />

                    <QueryHelp />
                </div>
            </SheetContent>
        </Sheet>
    );
}

type GroupFormProps = {
    vault: VaultSummary;
    group?: ColourGroup;
    defaultColour?: GroupColour;
    options: {
        only: string[];
        preserveScroll: boolean;
        preserveState: boolean;
    };
    onMoveUp?: () => void;
    onMoveDown?: () => void;
};

function GroupForm({
    vault,
    group,
    defaultColour = 'blue',
    options,
    onMoveUp,
    onMoveDown,
}: GroupFormProps) {
    const isNew = group === undefined;
    const form = useForm<{ name: string; query: string; colour: GroupColour }>({
        name: group?.name ?? '',
        query: group?.query ?? '',
        colour: group?.colour ?? defaultColour,
    });
    const count = useMatchCount(vault, form.data.query);

    const submit = () => {
        if (isNew) {
            // The list re-keys this form after a group is added, so it starts fresh.
            form.post(store(vault.id).url, options);
        } else {
            form.put(update({ vault: vault.id, colourGroup: group.id }).url, {
                ...options,
                onSuccess: () => form.setDefaults(),
            });
        }
    };

    return (
        <form
            className="space-y-2 rounded-lg border p-3"
            data-test={isNew ? 'new-colour-group' : 'colour-group'}
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
        >
            <div className="flex items-center gap-2">
                <ColourPicker
                    value={form.data.colour}
                    onChange={(colour) => form.setData('colour', colour)}
                />
                <Input
                    aria-label="Group name"
                    placeholder={isNew ? 'New group' : 'Name'}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    className="h-8"
                />
                {!isNew && (
                    <div className="flex shrink-0 items-center">
                        <IconButton
                            label="Move up"
                            disabled={!onMoveUp}
                            onClick={onMoveUp}
                        >
                            <ArrowUp />
                        </IconButton>
                        <IconButton
                            label="Move down"
                            disabled={!onMoveDown}
                            onClick={onMoveDown}
                        >
                            <ArrowDown />
                        </IconButton>
                        <IconButton
                            label="Delete group"
                            onClick={() =>
                                router.delete(
                                    destroy({
                                        vault: vault.id,
                                        colourGroup: group.id,
                                    }).url,
                                    options,
                                )
                            }
                        >
                            <Trash2 />
                        </IconButton>
                    </div>
                )}
            </div>
            <InputError message={form.errors.name} />

            <div className="flex items-center gap-2">
                <QueryInput
                    aria-label="Query"
                    placeholder="tag:project folder:Work"
                    value={form.data.query}
                    onValueChange={(query) => form.setData('query', query)}
                    className="h-8 font-mono text-xs"
                    wrapperClassName="min-w-0 flex-1"
                />
                <span
                    className="w-16 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                    data-test="colour-group-count"
                >
                    {count.count === null
                        ? ''
                        : `${count.count} ${count.count === 1 ? 'note' : 'notes'}`}
                </span>
            </div>
            <InputError
                message={form.errors.query ?? count.error ?? undefined}
            />

            {(isNew || form.isDirty) && (
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={
                            form.processing ||
                            form.data.name.trim() === '' ||
                            form.data.query.trim() === ''
                        }
                    >
                        {form.processing ? (
                            <Spinner />
                        ) : isNew ? (
                            <Plus />
                        ) : (
                            <Check />
                        )}
                        {isNew ? 'Add group' : 'Save'}
                    </Button>
                </div>
            )}
        </form>
    );
}

function ColourPicker({
    value,
    onChange,
}: {
    value: GroupColour;
    onChange: (colour: GroupColour) => void;
}) {
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`Colour: ${value}`}
                    className="flex size-8 shrink-0 items-center justify-center rounded-md border hover:bg-accent"
                >
                    <span
                        className="size-4 rounded-full"
                        style={{ background: groupColourVariable(value) }}
                    />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="grid w-auto grid-cols-5 gap-1 p-2"
            >
                {GROUP_COLOURS.map((colour) => (
                    <DropdownMenuItem
                        key={colour}
                        aria-label={colour}
                        onSelect={() => onChange(colour)}
                        className="flex size-8 items-center justify-center p-0"
                    >
                        <span
                            className="flex size-5 items-center justify-center rounded-full"
                            style={{ background: groupColourVariable(colour) }}
                        >
                            {colour === value && (
                                <Check className="size-3 text-white" />
                            )}
                        </span>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function IconButton({
    label,
    disabled = false,
    onClick,
    children,
}: {
    label: string;
    disabled?: boolean;
    onClick?: () => void;
    children: React.ReactNode;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            title={label}
            disabled={disabled}
            onClick={onClick}
            className="size-7"
        >
            {children}
        </Button>
    );
}

/**
 * Live count of notes a query matches, debounced while typing.
 */
function useMatchCount(
    vault: VaultSummary,
    query: string,
): { count: number | null; error: string | null } {
    const [result, setResult] = useState<{
        query: string;
        count: number | null;
        error: string | null;
    }>({ query: '', count: null, error: null });

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
                .then((response) => setResult({ query, ...response }))
                .catch((error: unknown) => {
                    if (!isAbortError(error)) {
                        setResult({ query, count: null, error: null });
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [vault.id, query]);

    return result.query === query && query.trim() !== ''
        ? { count: result.count, error: result.error }
        : { count: null, error: null };
}

function QueryHelp() {
    const rows: [string, string][] = [
        ['tag:project', 'has the tag, or one nested under it'],
        ['folder:Work', 'is in the folder or below it'],
        ['category:person', 'frontmatter field equals the value'],
        ['status:*', 'frontmatter field exists'],
        ['meeting', 'title contains the text'],
        ['-tag:archived', 'does not match'],
    ];

    return (
        <div className="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
            <p className="mb-2 font-medium text-foreground">Query syntax</p>
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                {rows.map(([example, meaning]) => (
                    <div key={example} className="contents">
                        <dt className="font-mono">{example}</dt>
                        <dd>{meaning}</dd>
                    </div>
                ))}
            </dl>
            <p className="mt-2">
                Separate terms with spaces to require all of them.
            </p>
        </div>
    );
}
