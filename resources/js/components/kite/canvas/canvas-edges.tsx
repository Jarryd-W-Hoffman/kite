import { anchor, colorValue, edgeGeometry, facingSide } from '@/lib/canvas';
import type { CanvasEdge, CanvasNode, CanvasSide, Point } from '@/lib/canvas';

const ARROW = 12;

const INTO: Record<CanvasSide, Point> = {
    top: { x: 0, y: 1 },
    right: { x: -1, y: 0 },
    bottom: { x: 0, y: -1 },
    left: { x: 1, y: 0 },
};

/** An arrowhead with its tip at a card's side, pointing into the card. */
function arrowhead(tip: Point, side: CanvasSide): string {
    const direction = INTO[side];
    const back = {
        x: tip.x - direction.x * ARROW,
        y: tip.y - direction.y * ARROW,
    };
    const across = {
        x: -direction.y * (ARROW / 2),
        y: direction.x * (ARROW / 2),
    };

    return [
        `${tip.x},${tip.y}`,
        `${back.x + across.x},${back.y + across.y}`,
        `${back.x - across.x},${back.y - across.y}`,
    ].join(' ');
}

export type EdgeLayout = {
    edge: CanvasEdge;
    path: string;
    middle: Point;
    from: { point: Point; side: CanvasSide };
    to: { point: Point; side: CanvasSide };
};

/** Where each edge runs, skipping any whose cards are missing. */
export function layoutEdges(
    nodes: CanvasNode[],
    edges: CanvasEdge[],
): EdgeLayout[] {
    const byId = new Map(nodes.map((node) => [node.id, node]));

    return edges.flatMap((edge) => {
        const from = byId.get(edge.fromNode);
        const to = byId.get(edge.toNode);

        if (!from || !to) {
            return [];
        }

        const fromSide = edge.fromSide ?? facingSide(from, to);
        const toSide = edge.toSide ?? facingSide(to, from);
        const fromPoint = anchor(from, fromSide);
        const toPoint = anchor(to, toSide);
        const { path, middle } = edgeGeometry(
            fromPoint,
            fromSide,
            toPoint,
            toSide,
        );

        return [
            {
                edge,
                path,
                middle,
                from: { point: fromPoint, side: fromSide },
                to: { point: toPoint, side: toSide },
            },
        ];
    });
}

/**
 * The connections between cards, drawn under them. Each has a wide invisible stroke so it's
 * easy to click.
 */
export function CanvasEdges({
    layouts,
    selected,
    readOnly,
    onSelect,
    onEditLabel,
    pending,
}: {
    layouts: EdgeLayout[];
    selected: Set<string>;
    readOnly: boolean;
    onSelect: (id: string, additive: boolean) => void;
    onEditLabel: (id: string) => void;
    /** A connection being dragged out of a card. */
    pending: { path: string } | null;
}) {
    return (
        <svg className="pointer-events-none absolute top-0 left-0 size-px overflow-visible">
            {layouts.map(({ edge, path, from, to }) => {
                const color =
                    colorValue(edge.color) ?? 'var(--muted-foreground)';
                const isSelected = selected.has(edge.id);
                const stroke = isSelected ? 'var(--primary)' : color;

                return (
                    <g
                        key={edge.id}
                        data-test="canvas-edge"
                        data-edge-id={edge.id}
                    >
                        <path
                            d={path}
                            fill="none"
                            stroke="transparent"
                            strokeWidth={18}
                            className="pointer-events-auto cursor-pointer"
                            onPointerDown={(event) => {
                                event.stopPropagation();
                                onSelect(edge.id, event.shiftKey);
                            }}
                            onDoubleClick={(event) => {
                                event.stopPropagation();

                                if (!readOnly) {
                                    onEditLabel(edge.id);
                                }
                            }}
                        />
                        <path
                            d={path}
                            fill="none"
                            stroke={stroke}
                            strokeWidth={isSelected ? 3 : 2}
                            strokeLinecap="round"
                        />
                        {(edge.toEnd ?? 'arrow') === 'arrow' && (
                            <polygon
                                points={arrowhead(to.point, to.side)}
                                fill={stroke}
                            />
                        )}
                        {edge.fromEnd === 'arrow' && (
                            <polygon
                                points={arrowhead(from.point, from.side)}
                                fill={stroke}
                            />
                        )}
                    </g>
                );
            })}
            {pending && (
                <path
                    d={pending.path}
                    fill="none"
                    stroke="var(--primary)"
                    strokeWidth={2}
                    strokeDasharray="6 4"
                    data-test="canvas-pending-edge"
                />
            )}
        </svg>
    );
}
