import type { Point } from '@/lib/canvas';

type Props = {
    name: string;
    label: string;
    at: Point;
    onDone: (label: string) => void;
};

/** A box for naming a group or labelling a connection, done on Enter, Escape or leaving it. */
export function CanvasLabelEditor({ name, label, at, onDone }: Props) {
    return (
        <input
            autoFocus
            aria-label={name}
            data-test="canvas-label-editor"
            defaultValue={label}
            className="absolute z-20 w-48 rounded-md border bg-background px-2 py-1 text-sm shadow-kite-sm outline-none focus:ring-2 focus:ring-ring"
            style={{ left: at.x, top: at.y }}
            onPointerDown={(event) => event.stopPropagation()}
            onKeyDown={(event) => {
                event.stopPropagation();

                if (event.key === 'Enter' || event.key === 'Escape') {
                    event.currentTarget.blur();
                }
            }}
            onBlur={(event) => onDone(event.target.value.trim())}
        />
    );
}
