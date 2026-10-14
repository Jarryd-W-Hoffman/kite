/**
 * Where a comment points: the quoted text plus a little context on each side. The context picks
 * between repeated quotes and rescues a comment whose quote was reworded.
 */
export type CommentAnchor = {
    quote: string;
    prefix: string;
    suffix: string;
};

export type AnchorRange = { from: number; to: number };

/** Characters of context stored on each side of the quote. */
export const CONTEXT_LENGTH = 32;

/** How far apart the prefix and suffix may be when the quote itself was rewritten. */
const MAX_REWRITE_GROWTH = 3;

/**
 * Describe a range of the document as an anchor that can be found again later.
 */
export function captureAnchor(
    doc: string,
    from: number,
    to: number,
): CommentAnchor {
    return {
        quote: doc.slice(from, to),
        prefix: doc.slice(Math.max(0, from - CONTEXT_LENGTH), from),
        suffix: doc.slice(to, to + CONTEXT_LENGTH),
    };
}

/**
 * Find an anchor in a document, or null when its text is gone (the comment is orphaned).
 *
 * Tries, in order: the exact quote; the quote with any whitespace changes (reflowed lines); and
 * the stored context with new text between it (the quote was reworded in place). Several
 * candidates are ranked by how much of the surrounding context still matches.
 */
export function findAnchor(
    doc: string,
    anchor: CommentAnchor,
): AnchorRange | null {
    if (anchor.quote === '') {
        return null;
    }

    return (
        best(doc, anchor, exactMatches(doc, anchor.quote)) ??
        best(doc, anchor, looseMatches(doc, anchor.quote)) ??
        rewritten(doc, anchor)
    );
}

function exactMatches(doc: string, quote: string): AnchorRange[] {
    const ranges: AnchorRange[] = [];
    let index = doc.indexOf(quote);

    while (index !== -1) {
        ranges.push({ from: index, to: index + quote.length });
        index = doc.indexOf(quote, index + 1);
    }

    return ranges;
}

function looseMatches(doc: string, quote: string): AnchorRange[] {
    const words = quote.trim().split(/\s+/).filter(Boolean).map(escapeRegExp);

    if (words.length === 0) {
        return [];
    }

    return Array.from(
        doc.matchAll(new RegExp(words.join('\\s+'), 'g')),
        (match) => ({
            from: match.index,
            to: match.index + match[0].length,
        }),
    );
}

/**
 * The quote changed but its surroundings did not: take whatever now sits between them.
 */
function rewritten(doc: string, anchor: CommentAnchor): AnchorRange | null {
    // Short or missing context would match almost anywhere.
    if (anchor.prefix.trim().length < 8 || anchor.suffix.trim().length < 8) {
        return null;
    }

    const limit = Math.max(anchor.quote.length * MAX_REWRITE_GROWTH, 80);
    const candidates: AnchorRange[] = [];

    for (const prefix of exactMatches(doc, anchor.prefix)) {
        const end = doc.indexOf(anchor.suffix, prefix.to);

        if (end > prefix.to && end - prefix.to <= limit) {
            candidates.push({ from: prefix.to, to: end });
        }
    }

    return candidates.length === 1 ? candidates[0] : null;
}

function best(
    doc: string,
    anchor: CommentAnchor,
    ranges: AnchorRange[],
): AnchorRange | null {
    let chosen: AnchorRange | null = null;
    let chosenScore = -1;

    for (const range of ranges) {
        const score =
            commonSuffixLength(doc.slice(0, range.from), anchor.prefix) +
            commonPrefixLength(doc.slice(range.to), anchor.suffix);

        if (score > chosenScore) {
            chosen = range;
            chosenScore = score;
        }
    }

    return chosen;
}

function commonPrefixLength(a: string, b: string): number {
    let length = 0;

    while (length < a.length && length < b.length && a[length] === b[length]) {
        length++;
    }

    return length;
}

function commonSuffixLength(a: string, b: string): number {
    let length = 0;

    while (
        length < a.length &&
        length < b.length &&
        a[a.length - 1 - length] === b[b.length - 1 - length]
    ) {
        length++;
    }

    return length;
}

function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
