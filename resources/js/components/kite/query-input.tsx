import { usePage } from '@inertiajs/react';
import { useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { ComponentProps, KeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';
import { folderSuggestions } from '@/lib/filter-suggestions';
import type { FilterSuggestion } from '@/lib/filter-suggestions';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: string;
    onValueChange: (value: string) => void;
    /** A bare input, for boxes styled around it; otherwise the standard text field. */
    plain?: boolean;
    wrapperClassName?: string;
};

/**
 * A text box for a filter query (`tag:project folder:Work`) that suggests folders while a
 * `folder:` filter is being typed, from any part of their path. Arrow keys move through the
 * suggestions, Enter or Tab picks one, and Escape hides them.
 */
export function QueryInput({
    value,
    onValueChange,
    plain = false,
    wrapperClassName,
    onKeyDown,
    onBlur,
    ...props
}: Props) {
    const folders = usePage<{ folders?: string[] }>().props.folders;
    const input = useRef<HTMLInputElement>(null);
    const pendingCursor = useRef<number | null>(null);
    const listId = useId();
    const [cursor, setCursor] = useState(0);
    const [focused, setFocused] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const [active, setActive] = useState(0);

    const suggestions = useMemo(
        () => folderSuggestions(value, cursor, folders ?? []),
        [value, cursor, folders],
    );
    const open = focused && !dismissed && suggestions.length > 0;
    const highlighted = Math.min(active, suggestions.length - 1);

    // Put the cursor after a picked suggestion once the new value is in the box.
    useLayoutEffect(() => {
        if (pendingCursor.current !== null && input.current !== null) {
            input.current.setSelectionRange(
                pendingCursor.current,
                pendingCursor.current,
            );
            setCursor(pendingCursor.current);
            pendingCursor.current = null;
        }
    }, [value]);

    const pick = (suggestion: FilterSuggestion) => {
        pendingCursor.current = suggestion.cursor;
        setDismissed(true);
        onValueChange(suggestion.query);
    };

    const keyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (open) {
            const move = { ArrowDown: 1, ArrowUp: -1 }[event.key];

            if (move !== undefined) {
                event.preventDefault();
                setActive(
                    (highlighted + move + suggestions.length) %
                        suggestions.length,
                );

                return;
            }

            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                pick(suggestions[highlighted]);

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setDismissed(true);

                return;
            }
        }

        onKeyDown?.(event);
    };

    const Field = plain ? 'input' : Input;

    return (
        <div className={cn('relative', wrapperClassName)}>
            <Field
                {...props}
                ref={input}
                value={value}
                role="combobox"
                aria-autocomplete="list"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                aria-activedescendant={
                    open ? `${listId}-${highlighted}` : undefined
                }
                data-query-input=""
                autoComplete="off"
                spellCheck={false}
                onChange={(event) => {
                    setCursor(event.target.selectionStart ?? 0);
                    setDismissed(false);
                    setActive(0);
                    onValueChange(event.target.value);
                }}
                onSelect={(event) =>
                    setCursor(event.currentTarget.selectionStart ?? 0)
                }
                onFocus={() => setFocused(true)}
                onBlur={(event) => {
                    setFocused(false);
                    onBlur?.(event);
                }}
                onKeyDown={keyDown}
            />
            {open && (
                <ul
                    id={listId}
                    role="listbox"
                    aria-label="Folders"
                    data-test="query-suggestions"
                    className="absolute top-full left-0 z-50 mt-1 max-h-64 w-max max-w-[min(28rem,90vw)] min-w-full overflow-y-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-kite-lg"
                >
                    {suggestions.map((suggestion, index) => (
                        <li
                            key={suggestion.label}
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={index === highlighted}
                            className={cn(
                                'cursor-pointer truncate rounded-sm px-2 py-1 font-mono text-xs',
                                index === highlighted &&
                                    'bg-accent text-accent-foreground',
                            )}
                            // Keep focus in the box, so picking doesn't count as leaving it.
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseEnter={() => setActive(index)}
                            onClick={() => pick(suggestion)}
                        >
                            {suggestion.label}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * For a dialog's `onEscapeKeyDown`: dialogs see Escape before the box does, so while
 * suggestions are showing, Escape hides them instead of closing the dialog.
 */
export function keepOpenWhileSuggesting(event: globalThis.KeyboardEvent): void {
    if (
        event.target instanceof HTMLElement &&
        event.target.matches('[data-query-input][aria-expanded=true]')
    ) {
        event.preventDefault();
    }
}
