import { type DiffLine, diffLines } from '@/lib/line-diff';

/**
 * A stretch of a merge: lines both sides agree on (with where they came from), or a conflict
 * where they disagree and someone has to choose.
 */
export type MergeChunk =
    | {
          type: 'clean';
          lines: string[];
          /** `base`: unchanged; `mine` or `theirs`: one side's change; `both`: the same change on both. */
          source: 'base' | 'mine' | 'theirs' | 'both';
      }
    | { type: 'conflict'; base: string[]; mine: string[]; theirs: string[] };

export type MergeChoice = 'mine' | 'theirs' | 'both';

/** A change to the base: its lines `start` to `end` (exclusive) become `lines`. */
type Edit = { start: number; end: number; lines: string[] };

const split = (text: string): string[] => (text === '' ? [] : text.split('\n'));

function editsOf(diff: DiffLine[]): Edit[] {
    const edits: Edit[] = [];
    let position = 0;
    let current: Edit | null = null;

    for (const line of diff) {
        if (line.type === 'same') {
            current = null;
            position++;

            continue;
        }

        if (current === null) {
            current = { start: position, end: position, lines: [] };
            edits.push(current);
        }

        if (line.type === 'removed') {
            current.end++;
            position++;
        } else {
            current.lines.push(line.text);
        }
    }

    return edits;
}

function applyEdits(
    base: string[],
    start: number,
    end: number,
    edits: Edit[],
): string[] {
    const lines: string[] = [];
    let position = start;

    for (const edit of edits) {
        lines.push(...base.slice(position, edit.start), ...edit.lines);
        position = edit.end;
    }

    return [...lines, ...base.slice(position, end)];
}

const sameLines = (a: string[], b: string[]): boolean =>
    a.length === b.length && a.every((line, index) => line === b[index]);

/**
 * Merge two versions that both started from `base`, line by line. A change made on one side only
 * is taken as it is; the same change on both sides is taken once; changes to the same lines that
 * differ are left as conflicts.
 */
export function mergeThreeWay(
    base: string,
    mine: string,
    theirs: string,
): MergeChunk[] {
    const baseLines = split(base);
    const ours = editsOf(diffLines(base, mine));
    const others = editsOf(diffLines(base, theirs));
    const chunks: MergeChunk[] = [];
    let position = 0;
    let o = 0;
    let t = 0;

    const pushClean = (
        lines: string[],
        source: 'base' | 'mine' | 'theirs' | 'both',
    ) => {
        if (lines.length > 0) {
            chunks.push({ type: 'clean', lines, source });
        }
    };

    while (o < ours.length || t < others.length) {
        const start = Math.min(
            ours[o]?.start ?? Infinity,
            others[t]?.start ?? Infinity,
        );

        pushClean(baseLines.slice(position, start), 'base');

        // Gather every edit on either side that touches the same stretch of the base.
        const mineGroup: Edit[] = [];
        const theirsGroup: Edit[] = [];
        let end = start;
        let grew = true;

        while (grew) {
            grew = false;

            const mineEdit = ours[o];
            const theirsEdit = others[t];

            if (
                mineEdit &&
                (mineEdit.start < end || mineEdit.start === start)
            ) {
                mineGroup.push(mineEdit);
                end = Math.max(end, mineEdit.end);
                o++;
                grew = true;
            }

            if (
                theirsEdit &&
                (theirsEdit.start < end || theirsEdit.start === start)
            ) {
                theirsGroup.push(theirsEdit);
                end = Math.max(end, theirsEdit.end);
                t++;
                grew = true;
            }
        }

        const mineLines = applyEdits(baseLines, start, end, mineGroup);
        const theirsLines = applyEdits(baseLines, start, end, theirsGroup);

        if (theirsGroup.length === 0) {
            pushClean(mineLines, 'mine');
        } else if (mineGroup.length === 0) {
            pushClean(theirsLines, 'theirs');
        } else if (sameLines(mineLines, theirsLines)) {
            pushClean(mineLines, 'both');
        } else {
            chunks.push({
                type: 'conflict',
                base: baseLines.slice(start, end),
                mine: mineLines,
                theirs: theirsLines,
            });
        }

        position = end;
    }

    pushClean(baseLines.slice(position), 'base');

    return chunks;
}

/**
 * Compare two versions with no known common starting point (such as a sync app's conflicting
 * copy): lines they share are kept, and every difference is left to choose.
 */
export function mergeTwoWay(mine: string, theirs: string): MergeChunk[] {
    const chunks: MergeChunk[] = [];

    for (const line of diffLines(mine, theirs)) {
        const last = chunks.at(-1);

        if (line.type === 'same') {
            if (last?.type === 'clean') {
                last.lines.push(line.text);
            } else {
                chunks.push({
                    type: 'clean',
                    lines: [line.text],
                    source: 'both',
                });
            }

            continue;
        }

        const conflict =
            last?.type === 'conflict'
                ? last
                : { type: 'conflict' as const, base: [], mine: [], theirs: [] };

        if (conflict !== last) {
            chunks.push(conflict);
        }

        (line.type === 'removed' ? conflict.mine : conflict.theirs).push(
            line.text,
        );
    }

    return chunks;
}

/**
 * The merged text, given a choice for each conflict in order (missing choices keep mine).
 */
export function resolveMerge(
    chunks: MergeChunk[],
    choices: MergeChoice[],
): string {
    let conflict = 0;
    const lines: string[] = [];

    for (const chunk of chunks) {
        if (chunk.type === 'clean') {
            lines.push(...chunk.lines);

            continue;
        }

        const choice = choices[conflict++] ?? 'mine';

        lines.push(
            ...(choice === 'theirs' ? [] : chunk.mine),
            ...(choice === 'mine' ? [] : chunk.theirs),
        );
    }

    return lines.join('\n');
}
