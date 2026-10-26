import { useState } from 'react';
import { clampWidth, widthCodec } from '@/lib/panel-width';
import type { WidthRange } from '@/lib/panel-width';
import { readStored, writeStored } from '@/lib/storage';

export type PanelWidth = {
    width: number;
    range: WidthRange;
    /** Set the width, remembering it unless `save` is false (mid-drag). */
    resize: (width: number, save?: boolean) => void;
};

/**
 * A panel's width, remembered between pages and launches.
 */
export function usePanelWidth(key: string, range: WidthRange): PanelWidth {
    const [width, setWidth] = useState(() =>
        readStored(key, widthCodec(range), range.min),
    );

    return {
        width,
        range,
        resize: (next, save = true) => {
            const clamped = clampWidth(next, range);

            setWidth(clamped);

            if (save) {
                writeStored(key, widthCodec(range), clamped);
            }
        },
    };
}
