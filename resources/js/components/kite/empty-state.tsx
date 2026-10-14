import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon: LucideIcon;
    title: string;
    description?: ReactNode;
    iconClassName?: string;
    /** Buttons or links offering a way forward. */
    children?: ReactNode;
    className?: string;
};

/**
 * A centred message for screens and panels with nothing to show, with an optional next step.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    iconClassName,
    children,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-4 p-8 text-center',
                className,
            )}
            data-test="empty-state"
        >
            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                <Icon
                    className={cn(
                        'size-6 text-muted-foreground',
                        iconClassName,
                    )}
                />
            </div>
            <div className="max-w-sm space-y-1">
                <h2 className="text-base font-semibold">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children && (
                <div className="flex flex-wrap items-center justify-center gap-2">
                    {children}
                </div>
            )}
        </div>
    );
}
