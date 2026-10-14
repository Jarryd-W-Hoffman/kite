import { usePage } from '@inertiajs/react';
import { formatShortcut } from '@/lib/shortcuts';
import { cn } from '@/lib/utils';

/**
 * A keyboard shortcut written for the current platform, e.g. `Mod+K` shows ⌘K on macOS and Ctrl+K elsewhere.
 */
export function Kbd({
    shortcut,
    className,
}: {
    shortcut: string;
    className?: string;
}) {
    const { kite } = usePage().props;

    return (
        <kbd
            className={cn(
                'rounded border px-1.5 py-0.5 font-sans text-[10px] text-muted-foreground',
                className,
            )}
        >
            {formatShortcut(shortcut, kite.platform)}
        </kbd>
    );
}
