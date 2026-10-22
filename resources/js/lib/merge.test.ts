import { describe, expect, test } from 'vite-plus/test';
import { mergeThreeWay, mergeTwoWay, resolveMerge } from '@/lib/merge';

const conflicts = (chunks: ReturnType<typeof mergeThreeWay>) =>
    chunks.filter((chunk) => chunk.type === 'conflict').length;

describe('mergeThreeWay', () => {
    const base = 'a\nb\nc\nd\ne';

    test('takes changes to different lines from both sides', () => {
        const chunks = mergeThreeWay(base, 'A\nb\nc\nd\ne', 'a\nb\nc\nd\nE');

        expect(conflicts(chunks)).toBe(0);
        expect(resolveMerge(chunks, [])).toBe('A\nb\nc\nd\nE');
    });

    test('takes the same change on both sides once', () => {
        const chunks = mergeThreeWay(base, 'a\nB\nc\nd\ne', 'a\nB\nc\nd\ne');

        expect(chunks).toContainEqual({
            type: 'clean',
            lines: ['B'],
            source: 'both',
        });
        expect(resolveMerge(chunks, [])).toBe('a\nB\nc\nd\ne');
    });

    test('leaves different changes to the same lines as a conflict', () => {
        const chunks = mergeThreeWay(
            base,
            'a\nmine\nc\nd\ne',
            'a\ntheirs\nc\nd\ne',
        );

        expect(chunks).toContainEqual({
            type: 'conflict',
            base: ['b'],
            mine: ['mine'],
            theirs: ['theirs'],
        });
        expect(resolveMerge(chunks, ['mine'])).toBe('a\nmine\nc\nd\ne');
        expect(resolveMerge(chunks, ['theirs'])).toBe('a\ntheirs\nc\nd\ne');
        expect(resolveMerge(chunks, ['both'])).toBe('a\nmine\ntheirs\nc\nd\ne');
    });

    test('treats lines added at the same place as a conflict', () => {
        const chunks = mergeThreeWay(
            base,
            'a\nb\nX\nc\nd\ne',
            'a\nb\nY\nc\nd\ne',
        );

        expect(conflicts(chunks)).toBe(1);
        expect(resolveMerge(chunks, ['both'])).toBe('a\nb\nX\nY\nc\nd\ne');
    });

    test('combines a deletion on one side with an addition on the other', () => {
        const chunks = mergeThreeWay(base, 'a\nc\nd\ne', 'a\nb\nc\nd\ne\nf');

        expect(resolveMerge(chunks, [])).toBe('a\nc\nd\ne\nf');
    });

    test('keeps unchanged text, empty bases and trailing newlines', () => {
        expect(resolveMerge(mergeThreeWay(base, base, base), [])).toBe(base);
        expect(resolveMerge(mergeThreeWay('', 'x', 'x'), [])).toBe('x');
        expect(resolveMerge(mergeThreeWay('a\n', 'a\nb\n', 'a\n'), [])).toBe(
            'a\nb\n',
        );
    });
});

describe('mergeTwoWay', () => {
    test('leaves every difference to choose', () => {
        const chunks = mergeTwoWay('a\nmine\nc', 'a\ntheirs\nc\nd');

        expect(conflicts(chunks)).toBe(2);
        expect(resolveMerge(chunks, ['theirs', 'theirs'])).toBe(
            'a\ntheirs\nc\nd',
        );
        expect(resolveMerge(chunks, ['mine', 'mine'])).toBe('a\nmine\nc');
    });

    test('keeps mine where no choice was made', () => {
        expect(resolveMerge(mergeTwoWay('a\nx', 'a\ny'), [])).toBe('a\nx');
    });
});
