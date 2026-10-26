import { useRef } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import type { PanelWidth } from '@/hooks/use-panel-width';
import { RESIZE_STEP } from '@/lib/panel-width';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    panel: PanelWidth;
    /** The panel edge the handle sits on: dragging away from the panel widens it. */
    edge: 'left' | 'right';
};

/**
 * A handle on a panel's edge that resizes it by dragging, or with the arrow keys once focused.
 * Double-clicking it, or pressing Home, puts the panel back to its narrowest.
 */
export function ResizeHandle({ label, panel, edge }: Props) {
    const drag = useRef<{ x: number; width: number; latest: number } | null>(
        null,
    );
    const direction = edge === 'right' ? 1 : -1;
    const { width, range, resize } = panel;

    const start = (event: PointerEvent<HTMLDivElement>) => {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = { x: event.clientX, width, latest: width };
        document.body.classList.add('kite-resizing');
    };

    const move = (event: PointerEvent<HTMLDivElement>) => {
        if (drag.current === null) {
            return;
        }

        drag.current.latest =
            drag.current.width + (event.clientX - drag.current.x) * direction;
        resize(drag.current.latest, false);
    };

    const end = () => {
        if (drag.current === null) {
            return;
        }

        resize(drag.current.latest);
        drag.current = null;
        document.body.classList.remove('kite-resizing');
    };

    const keyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const next = {
            ArrowLeft: width - RESIZE_STEP * direction,
            ArrowRight: width + RESIZE_STEP * direction,
            Home: range.min,
            End: range.max,
        }[event.key];

        if (next !== undefined) {
            event.preventDefault();
            resize(next);
        }
    };

    return (
        <div
            role="separator"
            aria-label={label}
            aria-orientation="vertical"
            aria-valuenow={width}
            aria-valuemin={range.min}
            aria-valuemax={range.max}
            tabIndex={0}
            data-test="resize-handle"
            className={cn(
                'kite-no-drag group absolute inset-y-0 z-20 w-2 cursor-col-resize touch-none outline-none',
                edge === 'right' ? '-right-1' : '-left-1',
            )}
            onPointerDown={start}
            onPointerMove={move}
            onPointerUp={end}
            onPointerCancel={end}
            onLostPointerCapture={end}
            onDoubleClick={() => resize(range.min)}
            onKeyDown={keyDown}
        >
            <div className="mx-auto h-full w-0.5 bg-transparent transition-colors group-hover:bg-primary/40 group-focus-visible:bg-primary group-active:bg-primary" />
        </div>
    );
}
