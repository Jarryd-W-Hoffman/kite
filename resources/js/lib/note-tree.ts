import type { TreeNote } from '@/types';

export type TreeFolder = {
    name: string;
    path: string;
    folders: TreeFolder[];
    notes: TreeNote[];
};

export function noteName(path: string): string {
    const file = path.split('/').pop() ?? path;

    return file.replace(/\.md$/i, '');
}

export function noteFolder(path: string): string {
    return path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : '';
}

/**
 * Turn a flat list of note paths into a nested folder tree, folders first, sorted naturally.
 */
export function buildTree(notes: TreeNote[]): TreeFolder {
    const root: TreeFolder = { name: '', path: '', folders: [], notes: [] };
    const folders = new Map<string, TreeFolder>([['', root]]);

    const folderFor = (path: string): TreeFolder => {
        const existing = folders.get(path);

        if (existing) {
            return existing;
        }

        const parent = folderFor(noteFolder(path));
        const folder: TreeFolder = {
            name: path.split('/').pop() ?? path,
            path,
            folders: [],
            notes: [],
        };

        parent.folders.push(folder);
        folders.set(path, folder);

        return folder;
    };

    for (const note of notes) {
        folderFor(noteFolder(note.path)).notes.push(note);
    }

    const collator = new Intl.Collator(undefined, {
        numeric: true,
        sensitivity: 'base',
    });

    const sort = (folder: TreeFolder): void => {
        folder.folders.sort((a, b) => collator.compare(a.name, b.name));
        folder.notes.sort((a, b) =>
            collator.compare(noteName(a.path), noteName(b.path)),
        );
        folder.folders.forEach(sort);
    };

    sort(root);

    return root;
}

/**
 * Rank notes for the [[ autocomplete: prefix matches first, then substring matches.
 */
export function matchNotes(
    notes: TreeNote[],
    query: string,
    limit = 8,
): TreeNote[] {
    const needle = query.trim().toLowerCase();

    const scored = notes
        .map((note) => {
            const name = noteName(note.path).toLowerCase();
            const title = note.title.toLowerCase();

            if (needle === '') {
                return { note, score: 2 };
            }

            if (name.startsWith(needle) || title.startsWith(needle)) {
                return { note, score: 0 };
            }

            if (name.includes(needle) || title.includes(needle)) {
                return { note, score: 1 };
            }

            return null;
        })
        .filter((entry) => entry !== null);

    scored.sort(
        (a, b) =>
            a.score - b.score ||
            noteName(a.note.path).localeCompare(noteName(b.note.path)),
    );

    return scored.slice(0, limit).map((entry) => entry.note);
}
