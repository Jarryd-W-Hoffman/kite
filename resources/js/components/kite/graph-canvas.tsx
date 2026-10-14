import { useEffect, useRef } from 'react';
import { readGroupColours } from '@/lib/colour-groups';
import type { ColourGroup, GroupColour } from '@/lib/colour-groups';
import { ForceSimulation, seedPositions } from '@/lib/force-graph';
import type { SimEdge, SimNode } from '@/lib/force-graph';
import { WebGLGraphRenderer } from '@/lib/graph-webgl';
import { cn } from '@/lib/utils';

export type GraphNode = {
    id: string;
    title: string;
    folder: string;
    tags?: string[];
    /** Colour group id from the server, when the note matches one. */
    group?: number | null;
    degree: number;
    ghost: boolean;
};

export type GraphEdge = {
    source: string;
    target: string;
};

type Props = {
    nodes: GraphNode[];
    edges: GraphEdge[];
    activeId?: string | null;
    highlight?: string;
    highlightTag?: string;
    colourBy?: 'folder' | 'tag' | 'group';
    /** The vault's colour groups, used when colouring by group. */
    groups?: ColourGroup[];
    renderer?: 'auto' | 'webgl' | 'canvas';
    webglThreshold?: number;
    onOpen: (node: GraphNode) => void;
    compact?: boolean;
    className?: string;
};

type RenderNode = SimNode & {
    data: GraphNode;
    radius: number;
    neighbours: Set<string>;
};

type Palette = {
    foreground: string;
    muted: string;
    edge: string;
    background: string;
    accent: string;
    groups: Record<GroupColour, string>;
};

function readPalette(element: HTMLElement): Palette {
    const style = getComputedStyle(element);
    const read = (name: string, fallback: string) =>
        style.getPropertyValue(name).trim() || fallback;

    return {
        foreground: read('--foreground', '#111'),
        muted: read('--muted-foreground', '#888'),
        edge: read('--kite-graph-edge', '#ccc'),
        background: read('--background', '#fff'),
        accent: read('--primary', 'oklch(0.53 0.2 278)'),
        groups: readGroupColours(element),
    };
}

/**
 * A stable, well-spread hue per top-level group (FNV-1a plus a Murmur3 finaliser).
 */
export function groupColour(group: string): string {
    if (group === '') {
        return 'oklch(0.62 0.14 260)';
    }

    let hash = 0x811c9dc5;

    for (const character of group.split('/')[0]) {
        hash ^= character.codePointAt(0) ?? 0;
        hash = Math.imul(hash, 0x01000193);
    }

    // MurmurHash3 finaliser: mixes every bit so near-identical names land far apart.
    hash ^= hash >>> 16;
    hash = Math.imul(hash, 0x85ebca6b);
    hash ^= hash >>> 13;
    hash = Math.imul(hash, 0xc2b2ae35);
    hash ^= hash >>> 16;

    return `oklch(0.68 0.15 ${(hash >>> 0) % 360})`;
}

export function GraphCanvas({
    nodes,
    edges,
    activeId = null,
    highlight = '',
    highlightTag = '',
    colourBy = 'folder',
    groups = [],
    renderer = 'auto',
    webglThreshold = 1000,
    onOpen,
    compact = false,
    className,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const glCanvasRef = useRef<HTMLCanvasElement>(null);
    const onOpenRef = useRef(onOpen);
    const highlightRef = useRef(highlight);
    const highlightTagRef = useRef(highlightTag);
    const colourByRef = useRef(colourBy);
    const groupsRef = useRef(new Map<number, ColourGroup>());
    const activeRef = useRef(activeId);
    const redrawRef = useRef<() => void>(() => undefined);

    useEffect(() => {
        onOpenRef.current = onOpen;
        highlightRef.current = highlight.trim().toLowerCase();
        highlightTagRef.current = highlightTag;
        colourByRef.current = colourBy;
        groupsRef.current = new Map(groups.map((group) => [group.id, group]));
        activeRef.current = activeId;
        redrawRef.current();
    });

    useEffect(() => {
        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');
        const glCanvas = glCanvasRef.current;

        if (!canvas || !context || !glCanvas) {
            return;
        }

        const wantsWebGL =
            renderer === 'webgl' ||
            (renderer === 'auto' && nodes.length >= webglThreshold);
        const gl = wantsWebGL ? WebGLGraphRenderer.create(glCanvas) : null;

        canvas.dataset.renderer = gl ? 'webgl' : 'canvas';

        const byId = new Map<string, RenderNode>();
        const renderNodes: RenderNode[] = nodes.map((data) => {
            const node: RenderNode = {
                id: data.id,
                x: 0,
                y: 0,
                vx: 0,
                vy: 0,
                fx: null,
                fy: null,
                degree: data.degree,
                data,
                radius: (compact ? 4 : 3) + Math.sqrt(data.degree) * 1.6,
                neighbours: new Set(),
            };

            byId.set(data.id, node);

            return node;
        });

        const renderEdges: SimEdge<RenderNode>[] = [];

        for (const edge of edges) {
            const source = byId.get(edge.source);
            const target = byId.get(edge.target);

            if (source && target) {
                renderEdges.push({ source, target });
                source.neighbours.add(target.id);
                target.neighbours.add(source.id);
            }
        }

        seedPositions(renderNodes);

        const simulation = new ForceSimulation(renderNodes, renderEdges, {
            linkDistance: compact ? 40 : 50,
        });

        for (let i = 0; i < (renderNodes.length > 2000 ? 30 : 120); i++) {
            simulation.tick();
        }

        let palette = readPalette(canvas);
        const transform = { x: 0, y: 0, k: 1 };
        let width = 0;
        let height = 0;
        let hovered: RenderNode | null = null;
        let frame = 0;
        let needsFit = true;

        const fit = () => {
            if (renderNodes.length === 0 || width === 0) {
                return;
            }

            let minX = Infinity;
            let minY = Infinity;
            let maxX = -Infinity;
            let maxY = -Infinity;

            for (const node of renderNodes) {
                minX = Math.min(minX, node.x);
                minY = Math.min(minY, node.y);
                maxX = Math.max(maxX, node.x);
                maxY = Math.max(maxY, node.y);
            }

            const padding = compact ? 40 : 80;
            const scale = Math.min(
                (width - padding * 2) / Math.max(maxX - minX, 1),
                (height - padding * 2) / Math.max(maxY - minY, 1),
                compact ? 1.6 : 2,
            );

            transform.k = Math.max(scale, 0.05);
            transform.x = width / 2 - ((minX + maxX) / 2) * transform.k;
            transform.y = height / 2 - ((minY + maxY) / 2) * transform.k;
        };

        const draw = () => {
            const dpr = window.devicePixelRatio || 1;
            const query = highlightRef.current;
            const tag = highlightTagRef.current;
            const hasTag = (node: RenderNode) =>
                tag === '' ||
                (node.data.tags ?? []).some(
                    (name) => name === tag || name.startsWith(`${tag}/`),
                );
            const active = activeRef.current;
            const focus = hovered;

            context.setTransform(dpr, 0, 0, dpr, 0, 0);
            context.clearRect(0, 0, width, height);
            context.translate(transform.x, transform.y);
            context.scale(transform.k, transform.k);

            const isLit = (node: RenderNode) =>
                (focus === null ||
                    focus === node ||
                    focus.neighbours.has(node.id)) &&
                (query === '' ||
                    node.data.title.toLowerCase().includes(query)) &&
                hasTag(node);

            const edgeAlpha = (touchesFocus: boolean) =>
                focus === null && query === '' && tag === ''
                    ? 0.7
                    : touchesFocus
                      ? 1
                      : 0.12;
            const nodeColour = (node: RenderNode) => {
                if (node === focus || node.id === active) {
                    return palette.accent;
                }

                if (colourByRef.current === 'group') {
                    const group = groupsRef.current.get(node.data.group ?? -1);

                    return group ? palette.groups[group.colour] : palette.muted;
                }

                return groupColour(
                    colourByRef.current === 'tag'
                        ? (node.data.tags?.[0] ?? '')
                        : node.data.folder,
                );
            };

            if (gl) {
                const glNodes = [];

                for (const node of renderNodes) {
                    if (node.id === active) {
                        glNodes.push({
                            x: node.x,
                            y: node.y,
                            radius: node.radius + 3 / transform.k,
                            colour: palette.foreground,
                            alpha: 1,
                            hollow: true,
                        });
                    }

                    glNodes.push({
                        x: node.x,
                        y: node.y,
                        radius: node.radius,
                        colour: node.data.ghost
                            ? palette.muted
                            : nodeColour(node),
                        alpha: isLit(node) ? 1 : 0.15,
                        hollow: node.data.ghost,
                    });
                }

                gl.draw({
                    width,
                    height,
                    dpr,
                    transform,
                    edges: renderEdges.map(({ source, target }) => {
                        const touchesFocus =
                            focus !== null &&
                            (source === focus || target === focus);

                        return {
                            x1: source.x,
                            y1: source.y,
                            x2: target.x,
                            y2: target.y,
                            colour: touchesFocus
                                ? palette.accent
                                : palette.edge,
                            alpha: edgeAlpha(touchesFocus),
                        };
                    }),
                    nodes: glNodes,
                });
            } else {
                context.lineWidth = 1 / transform.k;

                for (const { source, target } of renderEdges) {
                    const touchesFocus =
                        focus !== null &&
                        (source === focus || target === focus);

                    context.globalAlpha =
                        focus === null && query === '' && tag === ''
                            ? 0.7
                            : touchesFocus
                              ? 1
                              : 0.12;
                    context.strokeStyle = touchesFocus
                        ? palette.accent
                        : palette.edge;
                    context.beginPath();
                    context.moveTo(source.x, source.y);
                    context.lineTo(target.x, target.y);
                    context.stroke();
                }

                for (const node of renderNodes) {
                    context.globalAlpha = isLit(node) ? 1 : 0.15;
                    context.beginPath();
                    context.arc(node.x, node.y, node.radius, 0, Math.PI * 2);

                    if (node.data.ghost) {
                        context.fillStyle = palette.background;
                        context.fill();
                        context.strokeStyle = palette.muted;
                        context.lineWidth = 1.2 / transform.k;
                        context.stroke();
                    } else {
                        context.fillStyle = nodeColour(node);
                        context.fill();
                    }

                    if (node.id === active) {
                        context.strokeStyle = palette.foreground;
                        context.lineWidth = 2 / transform.k;
                        context.beginPath();
                        context.arc(
                            node.x,
                            node.y,
                            node.radius + 3 / transform.k,
                            0,
                            Math.PI * 2,
                        );
                        context.stroke();
                    }
                }
            }

            const fontSize = 12 / transform.k;
            context.font = `${fontSize}px ui-sans-serif, system-ui, sans-serif`;
            context.textAlign = 'center';
            context.textBaseline = 'top';

            for (const node of renderNodes) {
                const showLabel =
                    compact ||
                    node === focus ||
                    node.id === active ||
                    (focus !== null && focus.neighbours.has(node.id)) ||
                    ((query !== '' || tag !== '') && isLit(node)) ||
                    transform.k * (1 + node.degree * 0.15) > 1.4;

                if (!showLabel) {
                    continue;
                }

                context.globalAlpha = isLit(node) ? 1 : 0.25;
                context.fillStyle = node.data.ghost
                    ? palette.muted
                    : palette.foreground;
                context.fillText(
                    node.data.title.length > 40
                        ? `${node.data.title.slice(0, 39)}…`
                        : node.data.title,
                    node.x,
                    node.y + node.radius + 3 / transform.k,
                );
            }

            context.globalAlpha = 1;
        };

        const loop = () => {
            const moving = simulation.tick();

            if (needsFit) {
                fit();
                needsFit = moving;
            }

            draw();
            frame = moving ? requestAnimationFrame(loop) : 0;
        };

        const start = () => {
            if (frame === 0) {
                frame = requestAnimationFrame(loop);
            }
        };

        redrawRef.current = () => {
            if (frame === 0) {
                draw();
            }
        };

        const resize = () => {
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;

            width = rect.width;
            height = rect.height;
            canvas.width = Math.round(width * dpr);
            canvas.height = Math.round(height * dpr);
            glCanvas.width = canvas.width;
            glCanvas.height = canvas.height;

            if (needsFit) {
                fit();
            }

            draw();
        };

        const toWorld = (event: { clientX: number; clientY: number }) => {
            const rect = canvas.getBoundingClientRect();

            return {
                x: (event.clientX - rect.left - transform.x) / transform.k,
                y: (event.clientY - rect.top - transform.y) / transform.k,
            };
        };

        const nodeAt = (event: { clientX: number; clientY: number }) => {
            const point = toWorld(event);
            let closest: RenderNode | null = null;
            let closestDistance = Infinity;

            for (const node of renderNodes) {
                const distance = Math.hypot(node.x - point.x, node.y - point.y);
                const reach = node.radius + 6 / transform.k;

                if (distance < reach && distance < closestDistance) {
                    closest = node;
                    closestDistance = distance;
                }
            }

            return closest;
        };

        let pointer:
            | { mode: 'pan'; x: number; y: number; moved: boolean }
            | { mode: 'drag'; node: RenderNode; moved: boolean }
            | null = null;

        const onPointerDown = (event: PointerEvent) => {
            canvas.setPointerCapture(event.pointerId);
            const node = nodeAt(event);

            if (node) {
                pointer = { mode: 'drag', node, moved: false };
                node.fx = node.x;
                node.fy = node.y;
            } else {
                pointer = {
                    mode: 'pan',
                    x: event.clientX,
                    y: event.clientY,
                    moved: false,
                };
            }
        };

        const onPointerMove = (event: PointerEvent) => {
            if (pointer?.mode === 'drag') {
                const point = toWorld(event);
                needsFit = false;
                pointer.node.fx = point.x;
                pointer.node.fy = point.y;
                pointer.moved = true;
                simulation.reheat(0.3);
                start();

                return;
            }

            if (pointer?.mode === 'pan') {
                transform.x += event.clientX - pointer.x;
                transform.y += event.clientY - pointer.y;
                pointer.x = event.clientX;
                pointer.y = event.clientY;
                pointer.moved = true;
                needsFit = false;
                redrawRef.current();

                return;
            }

            const node = nodeAt(event);

            if (node !== hovered) {
                hovered = node;
                canvas.style.cursor = node ? 'pointer' : 'grab';
                redrawRef.current();
            }
        };

        const onPointerUp = (event: PointerEvent) => {
            canvas.releasePointerCapture(event.pointerId);

            if (pointer?.mode === 'drag') {
                pointer.node.fx = null;
                pointer.node.fy = null;

                if (!pointer.moved) {
                    onOpenRef.current(pointer.node.data);
                }
            }

            pointer = null;
        };

        const onPointerLeave = () => {
            if (hovered !== null && pointer === null) {
                hovered = null;
                redrawRef.current();
            }
        };

        const onWheel = (event: WheelEvent) => {
            event.preventDefault();

            const rect = canvas.getBoundingClientRect();
            const mouseX = event.clientX - rect.left;
            const mouseY = event.clientY - rect.top;
            const factor = Math.exp(
                -event.deltaY * (event.ctrlKey ? 0.01 : 0.002),
            );
            const k = Math.min(8, Math.max(0.05, transform.k * factor));

            transform.x = mouseX - ((mouseX - transform.x) / transform.k) * k;
            transform.y = mouseY - ((mouseY - transform.y) / transform.k) * k;
            transform.k = k;
            needsFit = false;
            redrawRef.current();
        };

        const onDoubleClick = (event: MouseEvent) => {
            if (!nodeAt(event)) {
                fit();
                redrawRef.current();
            }
        };

        const observer = new ResizeObserver(resize);
        observer.observe(canvas);

        const themeObserver = new MutationObserver(() => {
            palette = readPalette(canvas);
            redrawRef.current();
        });
        themeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        canvas.addEventListener('pointerdown', onPointerDown);
        canvas.addEventListener('pointermove', onPointerMove);
        canvas.addEventListener('pointerup', onPointerUp);
        canvas.addEventListener('pointerleave', onPointerLeave);
        canvas.addEventListener('wheel', onWheel, { passive: false });
        canvas.addEventListener('dblclick', onDoubleClick);

        resize();
        start();

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            themeObserver.disconnect();
            canvas.removeEventListener('pointerdown', onPointerDown);
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerup', onPointerUp);
            canvas.removeEventListener('pointerleave', onPointerLeave);
            canvas.removeEventListener('wheel', onWheel);
            canvas.removeEventListener('dblclick', onDoubleClick);
            redrawRef.current = () => undefined;
            gl?.destroy();
        };
    }, [nodes, edges, compact, renderer, webglThreshold]);

    return (
        <div className={cn('relative h-full w-full', className)}>
            <canvas
                ref={glCanvasRef}
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 h-full w-full"
            />
            <canvas
                ref={canvasRef}
                data-test="graph-canvas"
                role="img"
                aria-label={`Graph of ${nodes.length} notes and ${edges.length} links`}
                className="relative block h-full w-full cursor-grab touch-none"
            />
        </div>
    );
}
