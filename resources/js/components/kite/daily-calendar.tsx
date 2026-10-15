import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    /** Dates (YYYY-MM-DD) that have a daily note. */
    dates: string[];
    /** The date of the daily note being viewed, if any. */
    activeDate?: string | null;
    onOpen: (date: string) => void;
};

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function iso(date: Date): string {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * A month of days, Monday first, with a dot under each day that has a daily note. Clicking a
 * day opens that day's note, creating it from the daily template when there isn't one yet.
 */
export function DailyCalendar({ dates, activeDate = null, onOpen }: Props) {
    const [today] = useState(() => iso(new Date()));
    const [month, setMonth] = useState(() => {
        const start = activeDate ? new Date(`${activeDate}T00:00`) : new Date();

        return new Date(start.getFullYear(), start.getMonth(), 1);
    });
    const withNotes = useMemo(() => new Set(dates), [dates]);

    const days = useMemo(() => {
        const first = new Date(month);
        // Back up to the Monday on or before the 1st.
        first.setDate(1 - ((first.getDay() + 6) % 7));

        return Array.from({ length: 42 }, (_, index) => {
            const day = new Date(first);
            day.setDate(first.getDate() + index);

            return day;
        });
    }, [month]);

    const shift = (months: number) =>
        setMonth(new Date(month.getFullYear(), month.getMonth() + months, 1));

    return (
        <div className="px-1.5 pb-1" data-test="daily-calendar">
            <div className="mb-1 flex items-center">
                <button
                    type="button"
                    onClick={() => {
                        const now = new Date();
                        setMonth(
                            new Date(now.getFullYear(), now.getMonth(), 1),
                        );
                    }}
                    title="Back to this month"
                    className="flex-1 rounded-md px-1 py-0.5 text-left text-[13px] font-semibold hover:bg-sidebar-accent"
                    data-test="calendar-month"
                >
                    {month.toLocaleDateString(undefined, {
                        month: 'long',
                        year: 'numeric',
                    })}
                </button>
                <button
                    type="button"
                    onClick={() => shift(-1)}
                    aria-label="Previous month"
                    className="rounded-md p-1 text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                >
                    <ChevronLeft className="size-3.5" />
                </button>
                <button
                    type="button"
                    onClick={() => shift(1)}
                    aria-label="Next month"
                    className="rounded-md p-1 text-muted-foreground hover:bg-sidebar-accent hover:text-foreground"
                >
                    <ChevronRight className="size-3.5" />
                </button>
            </div>
            <div className="grid grid-cols-7 text-center text-[10.5px]">
                {WEEKDAYS.map((weekday) => (
                    <div
                        key={weekday}
                        className="pb-0.5 font-medium text-muted-foreground/70"
                    >
                        {weekday}
                    </div>
                ))}
                {days.map((day) => {
                    const date = iso(day);
                    const inMonth = day.getMonth() === month.getMonth();
                    const hasNote = withNotes.has(date);

                    return (
                        <button
                            key={date}
                            type="button"
                            onClick={() => onOpen(date)}
                            aria-label={`${day.toLocaleDateString(undefined, { dateStyle: 'full' })}${hasNote ? ', has a note' : ''}`}
                            aria-current={
                                date === activeDate ? 'date' : undefined
                            }
                            data-date={date}
                            data-has-note={hasNote}
                            className={cn(
                                'relative mx-auto flex size-7 items-center justify-center rounded-md text-[12px] tabular-nums transition-colors hover:bg-sidebar-accent',
                                !inMonth && 'text-muted-foreground/40',
                                date === today && 'font-semibold text-primary',
                                date === activeDate &&
                                    'bg-primary text-primary-foreground hover:bg-primary',
                            )}
                        >
                            {day.getDate()}
                            {hasNote && (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute bottom-0.5 size-1 rounded-full bg-primary/70',
                                        date === activeDate &&
                                            'bg-primary-foreground',
                                    )}
                                />
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
