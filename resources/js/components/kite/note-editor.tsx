import { FileText } from 'lucide-react';
import { useRef, useState } from 'react';
import { caretCoordinates } from '@/lib/caret';
import { matchNotes, noteName } from '@/lib/note-tree';
import { cn } from '@/lib/utils';
import type { TreeNote } from '@/types';

type Props = {
    value: string;
    onChange: (value: string) => void;
    onSave: () => void;
    notes: TreeNote[];
    className?: string;
};

type Suggestion = {
    start: number;
    query: string;
    top: number;
    left: number;
};

const LINK_TRIGGER = /\[\[([^[\]\n|#]*)$/;

export function NoteEditor({
    value,
    onChange,
    onSave,
    notes,
    className,
}: Props) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [suggestion, setSuggestion] = useState<Suggestion | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);

    const matches = suggestion ? matchNotes(notes, suggestion.query) : [];

    const updateSuggestion = (textarea: HTMLTextAreaElement) => {
        const caret = textarea.selectionStart;

        if (caret !== textarea.selectionEnd) {
            setSuggestion(null);

            return;
        }

        const match = LINK_TRIGGER.exec(textarea.value.slice(0, caret));

        if (!match) {
            setSuggestion(null);

            return;
        }

        const start = caret - match[1].length;
        const coordinates = caretCoordinates(textarea, start);

        if (suggestion?.start !== start || suggestion.query !== match[1]) {
            setActiveIndex(0);
        }

        setSuggestion({
            start,
            query: match[1],
            top: coordinates.top + coordinates.height,
            left: coordinates.left,
        });
    };

    const replaceRange = (
        start: number,
        end: number,
        text: string,
        selectFrom: number,
        selectTo: number = selectFrom,
    ) => {
        const textarea = textareaRef.current;

        if (!textarea) {
            return;
        }

        onChange(value.slice(0, start) + text + value.slice(end));

        requestAnimationFrame(() => {
            textarea.focus();
            textarea.setSelectionRange(selectFrom, selectTo);
        });
    };

    const insertLink = (note: TreeNote) => {
        const textarea = textareaRef.current;

        if (!textarea || !suggestion) {
            return;
        }

        const caret = textarea.selectionStart;
        const name = noteName(note.path);
        const closing = value.slice(caret, caret + 2) === ']]' ? 2 : 0;
        const inserted = `${name}]]`;

        replaceRange(
            suggestion.start,
            caret + closing,
            inserted,
            suggestion.start + inserted.length,
        );
        setSuggestion(null);
    };

    const wrapSelection = (marker: string) => {
        const textarea = textareaRef.current;

        if (!textarea) {
            return;
        }

        const { selectionStart: start, selectionEnd: end } = textarea;
        const selected = value.slice(start, end);

        replaceRange(
            start,
            end,
            `${marker}${selected}${marker}`,
            start + marker.length,
            end + marker.length,
        );
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (suggestion && matches.length > 0) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const delta = event.key === 'ArrowDown' ? 1 : -1;
                setActiveIndex(
                    (index) =>
                        (index + delta + matches.length) % matches.length,
                );

                return;
            }

            if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                insertLink(matches[activeIndex] ?? matches[0]);

                return;
            }
        }

        if (event.key === 'Escape' && suggestion) {
            event.preventDefault();
            setSuggestion(null);

            return;
        }

        const modifier = event.metaKey || event.ctrlKey;

        if (modifier && event.key === 's') {
            event.preventDefault();
            onSave();
        } else if (modifier && event.key === 'b') {
            event.preventDefault();
            wrapSelection('**');
        } else if (modifier && event.key === 'i') {
            event.preventDefault();
            wrapSelection('_');
        } else if (event.key === 'Tab' && !modifier) {
            event.preventDefault();
            const { selectionStart: start, selectionEnd: end } =
                event.currentTarget;
            replaceRange(start, end, '    ', start + 4);
        }
    };

    return (
        <div className={cn('relative h-full', className)}>
            <textarea
                ref={textareaRef}
                value={value}
                onChange={(event) => {
                    onChange(event.target.value);
                    updateSuggestion(event.target);
                }}
                onKeyDown={onKeyDown}
                onClick={(event) => updateSuggestion(event.currentTarget)}
                onKeyUp={(event) => {
                    if (
                        event.key === 'ArrowLeft' ||
                        event.key === 'ArrowRight'
                    ) {
                        updateSuggestion(event.currentTarget);
                    }
                }}
                onBlur={() => setSuggestion(null)}
                onScroll={(event) => {
                    if (suggestion) {
                        updateSuggestion(event.currentTarget);
                    }
                }}
                spellCheck
                aria-label="Note contents"
                data-test="note-editor"
                placeholder="Start writing… Use [[ to link notes."
                className="kite-editor block h-full w-full resize-none bg-transparent px-10 py-8 font-mono text-[14px] leading-7 text-foreground outline-none placeholder:text-muted-foreground"
            />

            {suggestion && matches.length > 0 && (
                <ul
                    role="listbox"
                    aria-label="Link suggestions"
                    className="absolute z-20 w-72 overflow-hidden rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
                    style={{
                        top: suggestion.top + 4,
                        left: Math.max(8, suggestion.left),
                    }}
                >
                    {matches.map((note, index) => (
                        <li
                            key={note.id}
                            role="option"
                            aria-selected={index === activeIndex}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                insertLink(note);
                            }}
                            onMouseMove={() => setActiveIndex(index)}
                            className={cn(
                                'flex cursor-pointer items-center gap-2 rounded px-2 py-1.5',
                                index === activeIndex && 'bg-accent',
                            )}
                        >
                            <FileText className="size-3.5 shrink-0 text-muted-foreground" />
                            <span className="truncate">
                                {noteName(note.path)}
                            </span>
                            {note.path.includes('/') && (
                                <span className="ml-auto truncate text-xs text-muted-foreground">
                                    {note.path.slice(
                                        0,
                                        note.path.lastIndexOf('/'),
                                    )}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
