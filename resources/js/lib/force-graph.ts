export type SimNode = {
    id: string;
    x: number;
    y: number;
    vx: number;
    vy: number;
    fx: number | null;
    fy: number | null;
    degree: number;
};

export type SimEdge<TNode extends SimNode = SimNode> = {
    source: TNode;
    target: TNode;
};

type QuadNode = {
    x0: number;
    y0: number;
    size: number;
    mass: number;
    cx: number;
    cy: number;
    body: SimNode | null;
    children: (QuadNode | null)[] | null;
};

export type SimulationOptions = {
    linkDistance: number;
    repulsion: number;
    gravity: number;
    theta: number;
    velocityDecay: number;
};

const DEFAULT_OPTIONS: SimulationOptions = {
    linkDistance: 50,
    repulsion: -140,
    gravity: 0.05,
    theta: 0.9,
    velocityDecay: 0.4,
};

const ALPHA_MIN = 0.002;
const ALPHA_DECAY = 1 - Math.pow(ALPHA_MIN, 1 / 300);

/**
 * Place nodes on a phyllotaxis spiral so the first frames are evenly spread out.
 */
export function seedPositions(nodes: SimNode[]): void {
    const angle = Math.PI * (3 - Math.sqrt(5));

    nodes.forEach((node, index) => {
        const radius = 10 * Math.sqrt(0.5 + index);

        node.x = radius * Math.cos(index * angle);
        node.y = radius * Math.sin(index * angle);
        node.vx = 0;
        node.vy = 0;
    });
}

function createQuad(x0: number, y0: number, size: number): QuadNode {
    return {
        x0,
        y0,
        size,
        mass: 0,
        cx: 0,
        cy: 0,
        body: null,
        children: null,
    };
}

function insert(quad: QuadNode, node: SimNode, depth = 0): void {
    quad.cx = (quad.cx * quad.mass + node.x) / (quad.mass + 1);
    quad.cy = (quad.cy * quad.mass + node.y) / (quad.mass + 1);
    quad.mass += 1;

    if (quad.children === null && quad.body === null) {
        quad.body = node;

        return;
    }

    if (depth > 32) {
        return;
    }

    if (quad.children === null) {
        quad.children = [null, null, null, null];

        const existing = quad.body;
        quad.body = null;

        if (existing) {
            insertIntoChild(quad, existing, depth);
        }
    }

    insertIntoChild(quad, node, depth);
}

function insertIntoChild(quad: QuadNode, node: SimNode, depth: number): void {
    const half = quad.size / 2;
    const right = node.x >= quad.x0 + half ? 1 : 0;
    const bottom = node.y >= quad.y0 + half ? 1 : 0;
    const index = bottom * 2 + right;
    const children = quad.children ?? [null, null, null, null];

    children[index] ??= createQuad(
        quad.x0 + right * half,
        quad.y0 + bottom * half,
        half,
    );

    quad.children = children;
    insert(children[index], node, depth + 1);
}

function buildQuadTree(nodes: SimNode[]): QuadNode | null {
    if (nodes.length === 0) {
        return null;
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const node of nodes) {
        minX = Math.min(minX, node.x);
        minY = Math.min(minY, node.y);
        maxX = Math.max(maxX, node.x);
        maxY = Math.max(maxY, node.y);
    }

    const size = Math.max(maxX - minX, maxY - minY, 1) + 1;
    const root = createQuad(minX, minY, size);

    for (const node of nodes) {
        insert(root, node);
    }

    return root;
}

export class ForceSimulation<TNode extends SimNode = SimNode> {
    alpha = 1;

    private readonly options: SimulationOptions;

    constructor(
        public readonly nodes: TNode[],
        public readonly edges: SimEdge<TNode>[],
        options: Partial<SimulationOptions> = {},
    ) {
        this.options = { ...DEFAULT_OPTIONS, ...options };
    }

    get isSettled(): boolean {
        return this.alpha < ALPHA_MIN;
    }

    reheat(alpha = 0.3): void {
        this.alpha = Math.max(this.alpha, alpha);
    }

    /**
     * Advance the simulation one step. Returns false once it has cooled down.
     */
    tick(): boolean {
        if (this.isSettled) {
            return false;
        }

        this.alpha += (0 - this.alpha) * ALPHA_DECAY;

        this.applyLinks();
        this.applyRepulsion();
        this.applyGravity();

        const decay = 1 - this.options.velocityDecay;

        for (const node of this.nodes) {
            if (node.fx !== null && node.fy !== null) {
                node.x = node.fx;
                node.y = node.fy;
                node.vx = 0;
                node.vy = 0;

                continue;
            }

            node.vx *= decay;
            node.vy *= decay;
            node.x += node.vx;
            node.y += node.vy;
        }

        return true;
    }

    private applyLinks(): void {
        const { linkDistance } = this.options;

        for (const { source, target } of this.edges) {
            let dx = target.x + target.vx - source.x - source.vx;
            let dy = target.y + target.vy - source.y - source.vy;
            let distance = Math.hypot(dx, dy) || 1e-6;

            const strength =
                1 / Math.max(1, Math.min(source.degree, target.degree));
            distance =
                ((distance - linkDistance) / distance) * this.alpha * strength;
            dx *= distance;
            dy *= distance;

            const total = source.degree + target.degree || 1;
            const bias = source.degree / total;

            target.vx -= dx * bias;
            target.vy -= dy * bias;
            source.vx += dx * (1 - bias);
            source.vy += dy * (1 - bias);
        }
    }

    private applyRepulsion(): void {
        const tree = buildQuadTree(this.nodes);

        if (!tree) {
            return;
        }

        const strength = this.options.repulsion * this.alpha;
        const theta2 = this.options.theta * this.options.theta;

        const visit = (quad: QuadNode, node: SimNode): void => {
            if (quad.mass === 0 || quad.body === node) {
                return;
            }

            const dx = quad.cx - node.x;
            const dy = quad.cy - node.y;
            const distance2 = dx * dx + dy * dy || 1e-3;

            if (
                quad.children === null ||
                (quad.size * quad.size) / distance2 < theta2
            ) {
                const mass = quad.body === null ? quad.mass : 1;
                const clamped = Math.max(distance2, 25);
                const force = (strength * mass) / clamped;

                node.vx += dx * force;
                node.vy += dy * force;

                return;
            }

            for (const child of quad.children) {
                if (child) {
                    visit(child, node);
                }
            }
        };

        for (const node of this.nodes) {
            visit(tree, node);
        }
    }

    private applyGravity(): void {
        const strength = this.options.gravity * this.alpha;

        for (const node of this.nodes) {
            node.vx -= node.x * strength;
            node.vy -= node.y * strength;
        }
    }
}
