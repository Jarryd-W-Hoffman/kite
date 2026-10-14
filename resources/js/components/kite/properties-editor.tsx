import {
    CalendarDays,
    CheckSquare,
    Hash,
    List,
    Plus,
    Type,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

export type Properties = Record<string, unknown>;

export type PropertyType =
    | 'text'
    | 'number'
    | 'date'
    | 'checkbox'
    | 'list'
    | 'other';

export const TYPES: Record<PropertyType, { label: string; icon: LucideIcon }> =
    {
        text: { label: 'Text', icon: Type },
        number: { label: 'Number', icon: Hash },
        date: { label: 'Date', icon: CalendarDays },
        checkbox: { label: 'Checkbox', icon: CheckSquare },
        list: { label: 'List', icon: List },
        other: { label: 'Other', icon: Type },
    };

const DATE = /^\d{4}-\d{2}-\d{2}$/;

export function typeOf(value: unknown): PropertyType {
    if (typeof value === 'boolean') {
        return 'checkbox';
    }

    if (typeof value === 'number') {
        return 'number';
    }

    if (Array.isArray(value)) {
        return 'list';
    }

    if (typeof value === 'string' && DATE.test(value)) {
        return 'date';
    }

    return value === null || typeof value === 'string' ? 'text' : 'other';
}

function emptyValue(type: PropertyType): unknown {
    const today = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return {
        text: '',
        number: 0,
        date: `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`,
        checkbox: false,
        list: [],
        other: '',
    }[type];
}

const inputClass =
    'h-7 w-full min-w-0 rounded-md border border-transparent bg-transparent px-1.5 text-[13px] outline-none transition-colors hover:bg-accent focus:border-border focus:bg-background';

type Props = {
    properties: Properties;
    onChange: (properties: Properties) => void;
};

/**
 * The note's frontmatter as typed fields: text, numbers, dates, checkboxes and lists. Every
 * change rewrites the frontmatter block of the note.
 */
export function PropertiesEditor({ properties, onChange }: Props) {
    const [adding, setAdding] = useState(false);
    const entries = Object.entries(properties);

    const set = (key: string, value: unknown) =>
        onChange({ ...properties, [key]: value });

    const rename = (from: string, to: string) => {
        const name = to.trim();

        if (name === '' || name === from || name in properties) {
            return;
        }

        onChange(
            Object.fromEntries(
                entries.map(([key, value]) => [
                    key === from ? name : key,
                    value,
                ]),
            ),
        );
    };

    const remove = (key: string) =>
        onChange(Object.fromEntries(entries.filter(([name]) => name !== key)));

    return (
        <div className="space-y-0.5" data-test="properties">
            {entries.map(([key, value]) => (
                <PropertyRow
                    key={key}
                    name={key}
                    value={value}
                    onRename={(name) => rename(key, name)}
                    onChange={(next) => set(key, next)}
                    onRemove={() => remove(key)}
                />
            ))}
            {adding ? (
                <NewProperty
                    existing={entries.map(([key]) => key)}
                    onAdd={(name, type) => {
                        setAdding(false);
                        set(name, emptyValue(type));
                    }}
                    onCancel={() => setAdding(false)}
                />
            ) : (
                <button
                    type="button"
                    onClick={() => setAdding(true)}
                    className="flex h-7 items-center gap-1.5 rounded-md px-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    data-test="add-property"
                >
                    <Plus className="size-3.5" />
                    Add property
                </button>
            )}
        </div>
    );
}

function PropertyRow({
    name,
    value,
    onRename,
    onChange,
    onRemove,
}: {
    name: string;
    value: unknown;
    onRename: (name: string) => void;
    onChange: (value: unknown) => void;
    onRemove: () => void;
}) {
    const type = typeOf(value);
    const Icon = TYPES[type].icon;

    return (
        <div
            className="group grid grid-cols-[6.5rem_1fr_auto] items-start gap-1"
            data-test="property"
            data-name={name}
            data-type={type}
        >
            <label className="flex h-7 min-w-0 items-center gap-1.5 text-muted-foreground">
                <Icon className="size-3.5 shrink-0" />
                <input
                    defaultValue={name}
                    aria-label={`Property name ${name}`}
                    onBlur={(event) => onRename(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.currentTarget.blur();
                        }
                    }}
                    className="h-7 w-full min-w-0 truncate rounded-md bg-transparent text-[13px] outline-none focus:text-foreground"
                />
            </label>
            <PropertyValue
                name={name}
                type={type}
                value={value}
                onChange={onChange}
            />
            <button
                type="button"
                onClick={onRemove}
                aria-label={`Remove ${name}`}
                className="flex size-7 items-center justify-center rounded-md text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:bg-accent focus-visible:opacity-100"
            >
                <X className="size-3.5" />
            </button>
        </div>
    );
}

function PropertyValue({
    name,
    type,
    value,
    onChange,
}: {
    name: string;
    type: PropertyType;
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    const label = `Property value ${name}`;
    const commit = (next: unknown) => {
        if (next !== value) {
            onChange(next);
        }
    };

    if (type === 'checkbox') {
        return (
            <div className="flex h-7 items-center px-1.5">
                <Checkbox
                    aria-label={label}
                    checked={value === true}
                    onCheckedChange={(checked) => onChange(checked === true)}
                />
            </div>
        );
    }

    if (type === 'list') {
        return (
            <ListValue
                label={label}
                items={(value as unknown[]).map(String)}
                onChange={onChange}
            />
        );
    }

    // Text, number and date values are strings or numbers (or empty).
    const scalar = value === null ? '' : String(value as string | number);

    if (type === 'other') {
        return (
            <code className="truncate px-1.5 py-1 text-xs text-muted-foreground">
                {JSON.stringify(value)}
            </code>
        );
    }

    return (
        <input
            key={scalar}
            aria-label={label}
            type={
                type === 'number' ? 'number' : type === 'date' ? 'date' : 'text'
            }
            defaultValue={scalar}
            placeholder="Empty"
            onBlur={(event) =>
                commit(
                    type === 'number'
                        ? Number(event.target.value)
                        : event.target.value,
                )
            }
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }
            }}
            className={cn(inputClass, type === 'number' && 'tabular-nums')}
        />
    );
}

function ListValue({
    label,
    items,
    onChange,
}: {
    label: string;
    items: string[];
    onChange: (items: string[]) => void;
}) {
    const [draft, setDraft] = useState('');

    const add = () => {
        const item = draft.trim().replace(/^#/, '');

        if (item !== '' && !items.includes(item)) {
            onChange([...items, item]);
        }

        setDraft('');
    };

    return (
        <div className="flex min-h-7 flex-wrap items-center gap-1 rounded-md px-1 py-0.5 hover:bg-accent/60">
            {items.map((item) => (
                <span
                    key={item}
                    className="inline-flex items-center gap-0.5 rounded-md bg-kite-accent-soft py-0.5 pr-0.5 pl-1.5 text-xs font-medium text-primary"
                >
                    {item}
                    <button
                        type="button"
                        aria-label={`Remove ${item}`}
                        onClick={() =>
                            onChange(items.filter((other) => other !== item))
                        }
                        className="rounded p-0.5 hover:bg-primary/15"
                    >
                        <X className="size-3" />
                    </button>
                </span>
            ))}
            <input
                aria-label={label}
                value={draft}
                placeholder={items.length === 0 ? 'Empty' : 'Add…'}
                onChange={(event) => setDraft(event.target.value)}
                onBlur={add}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ',') {
                        event.preventDefault();
                        add();
                    } else if (
                        event.key === 'Backspace' &&
                        draft === '' &&
                        items.length > 0
                    ) {
                        onChange(items.slice(0, -1));
                    }
                }}
                className="h-6 min-w-12 flex-1 bg-transparent px-0.5 text-[13px] outline-none"
            />
        </div>
    );
}

function NewProperty({
    existing,
    onAdd,
    onCancel,
}: {
    existing: string[];
    onAdd: (name: string, type: PropertyType) => void;
    onCancel: () => void;
}) {
    const [name, setName] = useState('');
    const [type, setType] = useState<PropertyType>('text');
    const valid =
        name.trim() !== '' &&
        !existing.includes(name.trim()) &&
        !/[:#\n[\]{},]/.test(name);

    return (
        <form
            className="flex items-center gap-1 rounded-lg border bg-background p-1"
            onSubmit={(event) => {
                event.preventDefault();

                if (valid) {
                    onAdd(name.trim(), type);
                }
            }}
            data-test="new-property"
        >
            <input
                autoFocus
                aria-label="New property name"
                placeholder="Property name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                onKeyDown={(event) => event.key === 'Escape' && onCancel()}
                className="h-7 min-w-0 flex-1 rounded-md bg-transparent px-1.5 text-[13px] outline-none"
            />
            <select
                aria-label="New property type"
                value={type}
                onChange={(event) =>
                    setType(event.target.value as PropertyType)
                }
                className="kite-select h-7 text-xs"
            >
                {(['text', 'number', 'date', 'checkbox', 'list'] as const).map(
                    (option) => (
                        <option key={option} value={option}>
                            {TYPES[option].label}
                        </option>
                    ),
                )}
            </select>
            <button
                type="submit"
                disabled={!valid}
                className="h-7 rounded-md bg-primary px-2 text-xs font-medium text-primary-foreground disabled:opacity-50"
            >
                Add
            </button>
        </form>
    );
}
