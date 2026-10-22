import { http } from '@inertiajs/react';

const HEADER = 'X-Kite-Timezone';

/**
 * Tell the server which timezone this window is in, so dates and times it writes into notes
 * (today's daily note, a quick capture's time, a template's {{time}}) are local, not UTC.
 */
export function installLocalTimezone(): void {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    http.onRequest((config) => ({
        ...config,
        headers: { ...config.headers, [HEADER]: timezone },
    }));
}
