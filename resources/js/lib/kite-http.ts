import { http } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';

type JsonRoute = RouteDefinition<'get' | 'post' | 'put' | 'delete'>;

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

    // 204 No Content has no body to decode.
    return (response.data ? JSON.parse(response.data) : undefined) as T;
}

export function isAbortError(error: unknown): boolean {
    return (
        error instanceof Error &&
        (error.name === 'AbortError' || error.name === 'HttpCancelledError')
    );
}

/**
 * Upload a file as multipart form data, returning the decoded JSON response.
 */
export async function uploadFile<T>(
    route: RouteDefinition<'post'>,
    file: File,
): Promise<T> {
    const data = new FormData();
    data.append('file', file);

    const response = await http.getClient().request({
        method: route.method,
        url: route.url,
        data,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    return JSON.parse(response.data) as T;
}
