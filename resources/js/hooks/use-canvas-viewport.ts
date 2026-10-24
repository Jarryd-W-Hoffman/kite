import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import { clampZoom, fitViewport } from '@/lib/canvas';
import type { Point, Rect, Viewport } from '@/lib/canvas';

/**
 * Where the canvas is looking: its pan and zoom, starting with every card in view.
 *
 * Pinch or Ctrl/Cmd-scroll zooms and scrolling pans (unless a card's content scrolls). Attach
 * `container` to the surface; `spaceHeld` says whether Space is down, when a drag pans.
 */
export function useCanvasViewport(nodes: Rect[]) {
    const container = useRef<HTMLDivElement>(null);
    const [size, setSize] = useState({ width: 0, height: 0 });
    const [viewport, setViewport] = useState<Viewport | null>(null);
    const spaceHeld = useRef(false);

    const view = viewport ?? { x: 0, y: 0, zoom: 1 };

    useLayoutEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setSize({
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            }),
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    // Start with every card in view.
    if (viewport === null && size.width > 0) {
        setViewport(fitViewport(nodes, size.width, size.height));
    }

    const toCanvas = useCallback(
        (clientX: number, clientY: number): Point => {
            const rect = container.current?.getBoundingClientRect();

            return {
                x: (clientX - (rect?.left ?? 0) - view.x) / view.zoom,
                y: (clientY - (rect?.top ?? 0) - view.y) / view.zoom,
            };
        },
        [view.x, view.y, view.zoom],
    );

    const center = (): Point => ({
        x: (size.width / 2 - view.x) / view.zoom,
        y: (size.height / 2 - view.y) / view.zoom,
    });

    const zoomAround = (
        factor: number,
        focus: Point = { x: size.width / 2, y: size.height / 2 },
    ) =>
        setViewport((current) => {
            const from = current ?? view;
            const zoom = clampZoom(from.zoom * factor);

            return {
                zoom,
                x: focus.x - ((focus.x - from.x) / from.zoom) * zoom,
                y: focus.y - ((focus.y - from.y) / from.zoom) * zoom,
            };
        });

    const fit = () => setViewport(fitViewport(nodes, size.width, size.height));

    useEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            const rect = element.getBoundingClientRect();

            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();
                zoomAround(Math.exp(-event.deltaY * 0.01), {
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top,
                });

                return;
            }

            const scroller = (event.target as HTMLElement).closest<HTMLElement>(
                '[data-canvas-scroll]',
            );

            if (scroller && scroller.scrollHeight > scroller.clientHeight) {
                return;
            }

            event.preventDefault();
            setViewport((current) =>
                current
                    ? {
                          ...current,
                          x: current.x - event.deltaX,
                          y: current.y - event.deltaY,
                      }
                    : current,
            );
        };

        element.addEventListener('wheel', onWheel, { passive: false });

        return () => element.removeEventListener('wheel', onWheel);
    });

    useEffect(() => {
        const down = (event: KeyboardEvent) => {
            if (
                event.code === 'Space' &&
                !(event.target as HTMLElement).closest('input, textarea')
            ) {
                spaceHeld.current = true;
            }
        };
        const up = (event: KeyboardEvent) => {
            if (event.code === 'Space') {
                spaceHeld.current = false;
            }
        };

        window.addEventListener('keydown', down);
        window.addEventListener('keyup', up);

        return () => {
            window.removeEventListener('keydown', down);
            window.removeEventListener('keyup', up);
        };
    }, []);

    return {
        container,
        view,
        setViewport,
        toCanvas,
        center,
        zoomAround,
        fit,
        spaceHeld,
    };
}
