import type { Codec } from '@/lib/storage';

/** How narrow and wide a resizable panel can be, in pixels. It starts at its narrowest. */
export type WidthRange = { min: number; max: number };

export const SIDEBAR_WIDTH: WidthRange = { min: 256, max: 448 };

export const DETAILS_WIDTH: WidthRange = { min: 288, max: 480 };

/** How far one arrow key press moves a resize handle. */
export const RESIZE_STEP = 16;

export function clampWidth(width: number, range: WidthRange): number {
    return Math.round(Math.min(range.max, Math.max(range.min, width)));
}

/** A stored width, kept within the range even if the range has changed since it was saved. */
export function widthCodec(range: WidthRange): Codec<number> {
    return {
        parse: (raw) => {
            const width = Number(raw);

            return raw.trim() !== '' && Number.isFinite(width)
                ? clampWidth(width, range)
                : undefined;
        },
        serialize: String,
    };
}
