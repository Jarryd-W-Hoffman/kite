/**
 * The colour group palette. Keys match the server's GroupColour enum; each maps to a --group-*
 * CSS variable with light and dark values.
 */
export const GROUP_COLOURS = [
    'red',
    'orange',
    'amber',
    'lime',
    'green',
    'teal',
    'sky',
    'blue',
    'violet',
    'pink',
] as const;

export type GroupColour = (typeof GROUP_COLOURS)[number];

export type ColourGroup = {
    id: number;
    name: string;
    query: string;
    colour: GroupColour;
};

export function groupColourVariable(colour: GroupColour): string {
    return `var(--group-${colour})`;
}

/**
 * Resolve the palette to concrete colour strings, for canvases that can't read CSS variables.
 */
export function readGroupColours(
    element: Element,
): Record<GroupColour, string> {
    const style = getComputedStyle(element);

    return Object.fromEntries(
        GROUP_COLOURS.map((colour) => [
            colour,
            style.getPropertyValue(`--group-${colour}`).trim() || '#888',
        ]),
    ) as Record<GroupColour, string>;
}
