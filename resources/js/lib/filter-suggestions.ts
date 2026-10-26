/**
 * Suggestions for the filter being typed in a query such as `tag:project folder:Wo`, so a
 * folder's whole path never has to be remembered: typing part of any folder's name offers it.
 */

export type FilterSuggestion = {
    /** The folder's path, as shown in the list. */
    label: string;
    /** The query with the suggestion in place of the value being typed. */
    query: string;
    /** Where the cursor goes afterwards: just past the suggestion. */
    cursor: number;
};

/** How many suggestions to show at once. */
export const MAX_SUGGESTIONS = 8;

type TypedFilter = { value: string; start: number; end: number };

/**
 * The `folder:` value the cursor is in, and where it starts and ends (including any quotes).
 */
export function typedFolderFilter(
    query: string,
    cursor: number,
): TypedFilter | null {
    // Terms are separated by spaces outside quotes, as the server reads them.
    let start = 0;
    let quoted = false;

    for (let index = 0; index < cursor; index++) {
        if (query[index] === '"') {
            quoted = !quoted;
        } else if (!quoted && /\s/.test(query[index])) {
            start = index + 1;
        }
    }

    let end = cursor;

    while (end < query.length && (quoted || !/\s/.test(query[end]))) {
        if (query[end] === '"') {
            quoted = !quoted;
        }

        end++;
    }

    const match = /^-?folder:/i.exec(query.slice(start, end));

    if (match === null || start + match[0].length > cursor) {
        return null;
    }

    const valueStart = start + match[0].length;

    return {
        value: query.slice(valueStart, end).replaceAll('"', ''),
        start: valueStart,
        end,
    };
}

/**
 * The folders worth suggesting for what's typed: those whose path contains it, best first
 * (names that start with it, then paths that do, then the rest), shallower folders before
 * deeper ones. A folder that's already typed exactly isn't suggested.
 */
export function rankFolders(folders: string[], typed: string): string[] {
    // A trailing slash narrows to what's inside the folder, so it's kept for matching.
    const needle = typed.trim().replace(/^\/+/, '').toLowerCase();
    const exact = needle.replace(/\/+$/, '');

    const rank = (folder: string): number => {
        const path = folder.toLowerCase();
        const name = path.slice(path.lastIndexOf('/') + 1);

        if (name.startsWith(needle)) {
            return 0;
        }

        return path.startsWith(needle) ? 1 : 2;
    };

    return folders
        .filter((folder) => {
            const path = folder.toLowerCase();

            return path.includes(needle) && path !== exact;
        })
        .map((folder) => ({
            folder,
            rank: rank(folder),
            depth: folder.split('/').length,
        }))
        .sort(
            (a, b) =>
                a.rank - b.rank ||
                a.depth - b.depth ||
                a.folder.localeCompare(b.folder, undefined, { numeric: true }),
        )
        .slice(0, MAX_SUGGESTIONS)
        .map(({ folder }) => folder);
}

/**
 * Folder suggestions for the cursor's place in the query, each with the query it would make.
 * Paths with spaces are quoted.
 */
export function folderSuggestions(
    query: string,
    cursor: number,
    folders: string[],
): FilterSuggestion[] {
    const typed = typedFolderFilter(query, cursor);

    if (typed === null) {
        return [];
    }

    return rankFolders(folders, typed.value).map((folder) => {
        const value = /\s/.test(folder) ? `"${folder}"` : folder;

        return {
            label: folder,
            query: query.slice(0, typed.start) + value + query.slice(typed.end),
            cursor: typed.start + value.length,
        };
    });
}
