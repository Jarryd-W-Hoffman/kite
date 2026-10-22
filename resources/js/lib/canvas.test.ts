import { describe, expect, test } from 'vite-plus/test';
import {
    anchor,
    endsOf,
    lineStyleOf,
    parallelogramSlant,
    reversed,
    shapeOf,
    withEnds,
    withLineStyle,
    withShape,
} from '@/lib/canvas';
import type { CanvasEdge, CanvasNode } from '@/lib/canvas';

const card: CanvasNode = {
    id: 'a',
    type: 'text',
    text: 'Hi',
    x: 0,
    y: 0,
    width: 200,
    height: 100,
};

const edge: CanvasEdge = {
    id: 'e',
    fromNode: 'a',
    toNode: 'b',
    fromSide: 'right',
    toSide: 'left',
};

describe('shapes', () => {
    test('a text card takes a shape where Advanced Canvas keeps it', () => {
        const diamond = withShape(card, 'diamond');

        expect(diamond.styleAttributes).toEqual({ shape: 'diamond' });
        expect(shapeOf(diamond)).toBe('diamond');
        expect(shapeOf(card)).toBeNull();
    });

    test("changing the shape keeps other apps' style attributes", () => {
        const styled = {
            ...card,
            styleAttributes: { border: 'dashed', shape: 'pill' },
        };

        expect(withShape(styled, 'circle').styleAttributes).toEqual({
            border: 'dashed',
            shape: 'circle',
        });
        expect(withShape(styled, null).styleAttributes).toEqual({
            border: 'dashed',
        });
        expect(withShape(withShape(card, 'pill'), null)).toEqual(card);
    });

    test("ignores shapes it doesn't know, and shapes on other cards", () => {
        expect(
            shapeOf({ ...card, styleAttributes: { shape: 'star' } }),
        ).toBeNull();
        expect(shapeOf({ ...card, styleAttributes: 'diamond' })).toBeNull();
        expect(
            shapeOf({
                id: 'g',
                type: 'group',
                x: 0,
                y: 0,
                width: 1,
                height: 1,
                styleAttributes: { shape: 'diamond' },
            }),
        ).toBeNull();
    });

    test('connections meet a parallelogram halfway along its slanted sides', () => {
        const slant = parallelogramSlant(200, 100);

        expect(anchor(withShape(card, 'parallelogram'), 'left')).toEqual({
            x: slant / 2,
            y: 50,
        });
        expect(anchor(withShape(card, 'parallelogram'), 'right')).toEqual({
            x: 200 - slant / 2,
            y: 50,
        });
        expect(anchor(withShape(card, 'diamond'), 'left')).toEqual({
            x: 0,
            y: 50,
        });
    });
});

describe('connections', () => {
    test('read and set which ends have arrowheads, defaulting as JSON Canvas does', () => {
        expect(endsOf(edge)).toBe('end');
        expect(endsOf({ ...edge, fromEnd: 'arrow' })).toBe('both');
        expect(endsOf({ ...edge, toEnd: 'none' })).toBe('none');
        expect(endsOf({ ...edge, fromEnd: 'arrow', toEnd: 'none' })).toBe(
            'end',
        );

        expect(withEnds(edge, 'both')).toMatchObject({
            fromEnd: 'arrow',
            toEnd: 'arrow',
        });
        expect(withEnds(edge, 'none')).toMatchObject({
            fromEnd: 'none',
            toEnd: 'none',
        });
        expect(withEnds(edge, 'end')).toMatchObject({
            fromEnd: 'none',
            toEnd: 'arrow',
        });
    });

    test('reverses a connection, its arrowhead pointing the other way', () => {
        expect(reversed({ ...edge, label: 'yes' })).toEqual({
            id: 'e',
            fromNode: 'b',
            toNode: 'a',
            fromSide: 'left',
            toSide: 'right',
            fromEnd: 'arrow',
            toEnd: 'none',
            label: 'yes',
        });
        expect(reversed(reversed(edge))).toEqual({
            ...edge,
            fromEnd: 'none',
            toEnd: 'arrow',
        });

        const { fromSide: _from, toSide: _to, ...sideless } = edge;

        expect(reversed(sideless)).not.toHaveProperty('fromSide');
    });

    test('draws lines solid, dashed or dotted', () => {
        expect(lineStyleOf(edge)).toBe('solid');
        expect(lineStyleOf(withLineStyle(edge, 'dotted'))).toBe('dotted');
        expect(
            withLineStyle(withLineStyle(edge, 'long-dashed'), 'solid'),
        ).toEqual(edge);
        expect(
            lineStyleOf({ ...edge, styleAttributes: { path: 'zigzag' } }),
        ).toBe('solid');
    });
});
