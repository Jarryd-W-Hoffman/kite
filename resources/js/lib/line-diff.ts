export type DiffLine = {
    type: 'same' | 'added' | 'removed';
    text: string;
};

export type DiffHunk =
    | { type: 'lines'; lines: DiffLine[] }
    | { type: 'skipped'; count: number };

/** Beyond this many line pairs, show a whole replacement rather than spend time finding matches. */
const MAX_CELLS = 4_000_000;

/**
 * A line-by-line diff from `before` to `after`, using the longest common subsequence of lines.
 */
export function diffLines(before: string, after: string): DiffLine[] {
    const a = before === '' ? [] : before.split('\n');
    const b = after === '' ? [] : after.split('\n');

    let start = 0;

    while (start < a.length && start < b.length && a[start] === b[start]) {
        start++;
    }

    let endA = a.length;
    let endB = b.length;

    while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) {
        endA--;
        endB--;
    }

    const same = (lines: string[]): DiffLine[] =>
        lines.map((text) => ({ type: 'same', text }));

    return [
        ...same(a.slice(0, start)),
        ...diffMiddle(a.slice(start, endA), b.slice(start, endB)),
        ...same(a.slice(endA)),
    ];
}

function diffMiddle(a: string[], b: string[]): DiffLine[] {
    if (a.length * b.length > MAX_CELLS) {
        return [
            ...a.map((text): DiffLine => ({ type: 'removed', text })),
            ...b.map((text): DiffLine => ({ type: 'added', text })),
        ];
    }

    // lengths[i][j]: longest common subsequence of a[i..] and b[j..].
    const width = b.length + 1;
    const lengths = new Uint32Array((a.length + 1) * width);

    for (let i = a.length - 1; i >= 0; i--) {
        for (let j = b.length - 1; j >= 0; j--) {
            lengths[i * width + j] =
                a[i] === b[j]
                    ? lengths[(i + 1) * width + j + 1] + 1
                    : Math.max(
                          lengths[(i + 1) * width + j],
                          lengths[i * width + j + 1],
                      );
        }
    }

    const lines: DiffLine[] = [];
    let i = 0;
    let j = 0;

    while (i < a.length && j < b.length) {
        if (a[i] === b[j]) {
            lines.push({ type: 'same', text: a[i] });
            i++;
            j++;
        } else if (lengths[(i + 1) * width + j] >= lengths[i * width + j + 1]) {
            lines.push({ type: 'removed', text: a[i++] });
        } else {
            lines.push({ type: 'added', text: b[j++] });
        }
    }

    while (i < a.length) {
        lines.push({ type: 'removed', text: a[i++] });
    }

    while (j < b.length) {
        lines.push({ type: 'added', text: b[j++] });
    }

    return lines;
}

/**
 * Keep changed lines with a little context around them, folding long unchanged stretches.
 */
export function toHunks(lines: DiffLine[], context = 3): DiffHunk[] {
    const keep = lines.map(() => false);

    lines.forEach((line, index) => {
        if (line.type !== 'same') {
            for (
                let k = Math.max(0, index - context);
                k <= Math.min(lines.length - 1, index + context);
                k++
            ) {
                keep[k] = true;
            }
        }
    });

    const hunks: DiffHunk[] = [];

    lines.forEach((line, index) => {
        const last = hunks.at(-1);

        if (keep[index]) {
            if (last?.type === 'lines') {
                last.lines.push(line);
            } else {
                hunks.push({ type: 'lines', lines: [line] });
            }
        } else if (last?.type === 'skipped') {
            last.count++;
        } else {
            hunks.push({ type: 'skipped', count: 1 });
        }
    });

    return hunks;
}

export function diffStats(lines: DiffLine[]): {
    added: number;
    removed: number;
} {
    return {
        added: lines.filter((line) => line.type === 'added').length,
        removed: lines.filter((line) => line.type === 'removed').length,
    };
}
