/**
 * WebGL2 renderer for the note graph. Nodes are point sprites shaded as anti-aliased circles,
 * edges are GL lines; labels stay on a 2D overlay because text is cheaper there.
 */

export type GLNode = {
    x: number;
    y: number;
    radius: number;
    colour: string;
    alpha: number;
    hollow: boolean;
};

export type GLEdge = {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    colour: string;
    alpha: number;
};

export type GLFrame = {
    width: number;
    height: number;
    dpr: number;
    transform: { x: number; y: number; k: number };
    edges: GLEdge[];
    nodes: GLNode[];
};

const POINT_VERTEX = `#version 300 es
in vec2 a_position;
in float a_radius;
in vec4 a_colour;
in float a_hollow;
uniform vec3 u_transform;
uniform vec2 u_resolution;
uniform float u_dpr;
out vec4 v_colour;
out float v_hollow;
out float v_size;

void main() {
    vec2 screen = a_position * u_transform.z + u_transform.xy;
    vec2 clip = screen / u_resolution * 2.0 - 1.0;
    gl_Position = vec4(clip.x, -clip.y, 0.0, 1.0);
    v_size = max(a_radius * 2.0 * u_transform.z * u_dpr + 2.0, 2.0);
    gl_PointSize = v_size;
    v_colour = a_colour;
    v_hollow = a_hollow;
}`;

const POINT_FRAGMENT = `#version 300 es
precision mediump float;
in vec4 v_colour;
in float v_hollow;
in float v_size;
out vec4 outColour;

void main() {
    float distance = length(gl_PointCoord * 2.0 - 1.0);
    float edge = 2.0 / v_size;
    float alpha = 1.0 - smoothstep(1.0 - edge, 1.0, distance);

    if (v_hollow > 0.5) {
        float thickness = clamp(5.0 / v_size, edge, 0.5);
        alpha *= smoothstep(1.0 - thickness - edge, 1.0 - thickness, distance);
    }

    if (alpha <= 0.0) {
        discard;
    }

    outColour = vec4(v_colour.rgb, v_colour.a * alpha);
}`;

const LINE_VERTEX = `#version 300 es
in vec2 a_position;
in vec4 a_colour;
uniform vec3 u_transform;
uniform vec2 u_resolution;
out vec4 v_colour;

void main() {
    vec2 screen = a_position * u_transform.z + u_transform.xy;
    vec2 clip = screen / u_resolution * 2.0 - 1.0;
    gl_Position = vec4(clip.x, -clip.y, 0.0, 1.0);
    v_colour = a_colour;
}`;

const LINE_FRAGMENT = `#version 300 es
precision mediump float;
in vec4 v_colour;
out vec4 outColour;

void main() {
    outColour = v_colour;
}`;

const POINT_STRIDE = 8;
const LINE_STRIDE = 6;

type Program = {
    program: WebGLProgram;
    attributes: Record<string, number>;
    uniforms: Record<string, WebGLUniformLocation | null>;
};

function compile(
    gl: WebGL2RenderingContext,
    vertexSource: string,
    fragmentSource: string,
    attributes: string[],
    uniforms: string[],
): Program {
    const shader = (type: number, source: string) => {
        const compiled = gl.createShader(type);

        if (!compiled) {
            throw new Error('Could not create a WebGL shader.');
        }

        gl.shaderSource(compiled, source);
        gl.compileShader(compiled);

        if (!gl.getShaderParameter(compiled, gl.COMPILE_STATUS)) {
            throw new Error(
                gl.getShaderInfoLog(compiled) ?? 'Shader failed to compile.',
            );
        }

        return compiled;
    };

    const program = gl.createProgram();

    gl.attachShader(program, shader(gl.VERTEX_SHADER, vertexSource));
    gl.attachShader(program, shader(gl.FRAGMENT_SHADER, fragmentSource));
    gl.linkProgram(program);

    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        throw new Error(
            gl.getProgramInfoLog(program) ?? 'Program failed to link.',
        );
    }

    return {
        program,
        attributes: Object.fromEntries(
            attributes.map((name) => [
                name,
                gl.getAttribLocation(program, name),
            ]),
        ),
        uniforms: Object.fromEntries(
            uniforms.map((name) => [
                name,
                gl.getUniformLocation(program, name),
            ]),
        ),
    };
}

/**
 * Resolve any CSS colour (including oklch() and color-mix()) to RGBA floats, cached per string.
 */
function createColourResolver(): (
    colour: string,
) => [number, number, number, number] {
    const cache = new Map<string, [number, number, number, number]>();
    const canvas = document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    const context = canvas.getContext('2d', { willReadFrequently: true });

    return (colour) => {
        const cached = cache.get(colour);

        if (cached) {
            return cached;
        }

        let rgba: [number, number, number, number] = [0.5, 0.5, 0.5, 1];

        if (context) {
            context.clearRect(0, 0, 1, 1);
            context.fillStyle = colour;
            context.fillRect(0, 0, 1, 1);

            const [r, g, b, a] = context.getImageData(0, 0, 1, 1).data;
            rgba = [r / 255, g / 255, b / 255, a / 255];
        }

        cache.set(colour, rgba);

        return rgba;
    };
}

export class WebGLGraphRenderer {
    private pointData = new Float32Array(0);

    private lineData = new Float32Array(0);

    private readonly resolveColour = createColourResolver();

    private constructor(
        private readonly gl: WebGL2RenderingContext,
        private readonly points: Program,
        private readonly lines: Program,
        private readonly pointBuffer: WebGLBuffer,
        private readonly lineBuffer: WebGLBuffer,
    ) {}

    /**
     * Create a renderer, or return null when WebGL2 is unavailable so callers can fall back to 2D.
     */
    static create(canvas: HTMLCanvasElement): WebGLGraphRenderer | null {
        const gl = canvas.getContext('webgl2', {
            antialias: true,
            premultipliedAlpha: false,
        });

        if (!gl) {
            return null;
        }

        try {
            const points = compile(
                gl,
                POINT_VERTEX,
                POINT_FRAGMENT,
                ['a_position', 'a_radius', 'a_colour', 'a_hollow'],
                ['u_transform', 'u_resolution', 'u_dpr'],
            );
            const lines = compile(
                gl,
                LINE_VERTEX,
                LINE_FRAGMENT,
                ['a_position', 'a_colour'],
                ['u_transform', 'u_resolution'],
            );

            gl.enable(gl.BLEND);
            gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

            return new WebGLGraphRenderer(
                gl,
                points,
                lines,
                gl.createBuffer(),
                gl.createBuffer(),
            );
        } catch {
            return null;
        }
    }

    draw(frame: GLFrame): void {
        const { gl } = this;

        gl.viewport(0, 0, gl.drawingBufferWidth, gl.drawingBufferHeight);
        gl.clearColor(0, 0, 0, 0);
        gl.clear(gl.COLOR_BUFFER_BIT);

        this.drawLines(frame);
        this.drawPoints(frame);
    }

    destroy(): void {
        this.gl.deleteBuffer(this.pointBuffer);
        this.gl.deleteBuffer(this.lineBuffer);
        this.gl.deleteProgram(this.points.program);
        this.gl.deleteProgram(this.lines.program);
    }

    private drawLines(frame: GLFrame): void {
        const { gl, lines } = this;
        const size = frame.edges.length * 2 * LINE_STRIDE;

        if (this.lineData.length < size) {
            this.lineData = new Float32Array(size);
        }

        frame.edges.forEach((edge, index) => {
            const [r, g, b, a] = this.resolveColour(edge.colour);
            const offset = index * 2 * LINE_STRIDE;

            this.lineData.set(
                [
                    edge.x1,
                    edge.y1,
                    r,
                    g,
                    b,
                    a * edge.alpha,
                    edge.x2,
                    edge.y2,
                    r,
                    g,
                    b,
                    a * edge.alpha,
                ],
                offset,
            );
        });

        gl.useProgram(lines.program);
        this.setTransform(lines, frame);

        gl.bindBuffer(gl.ARRAY_BUFFER, this.lineBuffer);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            this.lineData.subarray(0, size),
            gl.DYNAMIC_DRAW,
        );
        this.attribute(lines, 'a_position', 2, LINE_STRIDE, 0);
        this.attribute(lines, 'a_colour', 4, LINE_STRIDE, 2);

        gl.drawArrays(gl.LINES, 0, frame.edges.length * 2);
    }

    private drawPoints(frame: GLFrame): void {
        const { gl, points } = this;
        const size = frame.nodes.length * POINT_STRIDE;

        if (this.pointData.length < size) {
            this.pointData = new Float32Array(size);
        }

        frame.nodes.forEach((node, index) => {
            const [r, g, b, a] = this.resolveColour(node.colour);

            this.pointData.set(
                [
                    node.x,
                    node.y,
                    node.radius,
                    r,
                    g,
                    b,
                    a * node.alpha,
                    node.hollow ? 1 : 0,
                ],
                index * POINT_STRIDE,
            );
        });

        gl.useProgram(points.program);
        this.setTransform(points, frame);
        gl.uniform1f(points.uniforms.u_dpr, frame.dpr);

        gl.bindBuffer(gl.ARRAY_BUFFER, this.pointBuffer);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            this.pointData.subarray(0, size),
            gl.DYNAMIC_DRAW,
        );
        this.attribute(points, 'a_position', 2, POINT_STRIDE, 0);
        this.attribute(points, 'a_radius', 1, POINT_STRIDE, 2);
        this.attribute(points, 'a_colour', 4, POINT_STRIDE, 3);
        this.attribute(points, 'a_hollow', 1, POINT_STRIDE, 7);

        gl.drawArrays(gl.POINTS, 0, frame.nodes.length);
    }

    private setTransform(program: Program, frame: GLFrame): void {
        this.gl.uniform3f(
            program.uniforms.u_transform,
            frame.transform.x,
            frame.transform.y,
            frame.transform.k,
        );
        this.gl.uniform2f(
            program.uniforms.u_resolution,
            frame.width,
            frame.height,
        );
    }

    private attribute(
        program: Program,
        name: string,
        size: number,
        stride: number,
        offset: number,
    ): void {
        const location = program.attributes[name];

        if (location < 0) {
            return;
        }

        this.gl.enableVertexAttribArray(location);
        this.gl.vertexAttribPointer(
            location,
            size,
            this.gl.FLOAT,
            false,
            stride * Float32Array.BYTES_PER_ELEMENT,
            offset * Float32Array.BYTES_PER_ELEMENT,
        );
    }
}
