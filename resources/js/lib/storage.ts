import { useState } from 'react';

/**
 * Small per-window preferences kept in the browser's storage: open panels, view modes, tabs.
 *
 * Storage can be unavailable (blocked, private windows), full, or hold something unexpected from
 * an older version or a hand edit, so nothing here throws: reads fall back to a default and
 * failed writes are ignored. Values keep the plain formats they were always stored in.
 */

/** How a value is stored as text, and read back; `parse` returns undefined for anything invalid. */
export type Codec<T> = {
    parse: (raw: string) => T | undefined;
    serialize: (value: T) => string;
};

type Area = 'local' | 'session';

function area(name: Area): Storage | null {
    try {
        return name === 'local' ? window.localStorage : window.sessionStorage;
    } catch {
        return null;
    }
}

export function readStored<T, F = T>(
    key: string,
    codec: Codec<T>,
    fallback: F,
    from: Area = 'local',
): T | F {
    try {
        const raw = area(from)?.getItem(key) ?? null;

        return raw === null ? fallback : (codec.parse(raw) ?? fallback);
    } catch {
        return fallback;
    }
}

/** Store a value, or remove it when null. */
export function writeStored<T>(
    key: string,
    codec: Codec<T>,
    value: T | null,
    to: Area = 'local',
): void {
    try {
        if (value === null) {
            area(to)?.removeItem(key);
        } else {
            area(to)?.setItem(key, codec.serialize(value));
        }
    } catch {
        // Not being able to remember a preference shouldn't break anything.
    }
}

/**
 * State that starts from storage and is written back whenever it's set.
 */
export function useStoredState<T>(
    key: string,
    codec: Codec<T>,
    fallback: T,
): [T, (value: T) => void] {
    const [value, setValue] = useState(() => readStored(key, codec, fallback));

    return [
        value,
        (next: T) => {
            writeStored(key, codec, next);
            setValue(next);
        },
    ];
}

/** `true` / `false`. */
export const booleanCodec: Codec<boolean> = {
    parse: (raw) =>
        raw === 'true' ? true : raw === 'false' ? false : undefined,
    serialize: String,
};

/** A whole number above zero, such as a note id. */
export const idCodec: Codec<number> = {
    parse: (raw) => {
        const value = Number(raw);

        return Number.isInteger(value) && value > 0 ? value : undefined;
    },
    serialize: String,
};

/** One of a fixed set of words. */
export function oneOfCodec<T extends string>(values: readonly T[]): Codec<T> {
    return {
        parse: (raw) => values.find((value) => value === raw),
        serialize: (value) => value,
    };
}

/** JSON, accepted only when `isValid` says it has the expected shape. */
export function jsonCodec<T>(
    isValid: (value: unknown) => value is T,
): Codec<T> {
    return {
        parse: (raw) => {
            try {
                const value: unknown = JSON.parse(raw);

                return isValid(value) ? value : undefined;
            } catch {
                return undefined;
            }
        },
        serialize: (value) => JSON.stringify(value),
    };
}

export function isStringList(value: unknown): value is string[] {
    return (
        Array.isArray(value) && value.every((item) => typeof item === 'string')
    );
}

/** A list of strings, e.g. the folders folded away in the file tree. */
export const stringListCodec = jsonCodec(isStringList);
