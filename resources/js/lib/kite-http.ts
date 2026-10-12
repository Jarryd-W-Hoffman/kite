import { http } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';

type JsonRoute = RouteDefinition<'get' | 'post' | 'put'>;

/**
 * Send a JSON request through Inertia's HTTP client so CSRF handling stays consistent.
 */
export async function requestJson<T>(
    route: JsonRoute,
    data?: Record<string, unknown>,
    signal?: AbortSignal,
): Promise<T> {
    const response = await http.getClient().request({
        method: route.method,
        url: route.url,
        data,
        signal,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    return JSON.parse(response.data) as T;
}

export function isAbortError(error: unknown): boolean {
    return (
        error instanceof Error &&
        (error.name === 'AbortError' || error.name === 'HttpCancelledError')
    );
}
