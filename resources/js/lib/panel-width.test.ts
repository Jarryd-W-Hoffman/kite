import { describe, expect, test } from 'vite-plus/test';
import { clampWidth, widthCodec } from '@/lib/panel-width';

const range = { min: 200, max: 400 };

describe('clampWidth', () => {
    test('keeps widths within the range, in whole pixels', () => {
        expect(clampWidth(150, range)).toBe(200);
        expect(clampWidth(260.6, range)).toBe(261);
        expect(clampWidth(900, range)).toBe(400);
    });
});

describe('widthCodec', () => {
    const codec = widthCodec(range);

    test('reads stored widths back within the range', () => {
        expect(codec.parse('300')).toBe(300);
        expect(codec.parse('50')).toBe(200);
        expect(codec.parse('5000')).toBe(400);
        expect(codec.serialize(320)).toBe('320');
    });

    test('ignores anything that is not a number', () => {
        expect(codec.parse('')).toBeUndefined();
        expect(codec.parse('wide')).toBeUndefined();
        expect(codec.parse('NaN')).toBeUndefined();
        expect(codec.parse('Infinity')).toBeUndefined();
    });
});
