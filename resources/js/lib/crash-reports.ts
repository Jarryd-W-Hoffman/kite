import { requestJson } from '@/lib/kite-http';
import { store as reportCrash } from '@/routes/crash-reports';

/** A page that keeps failing shouldn't keep posting; a few distinct errors are plenty. */
const MAX_REPORTS = 10;

/**
 * Pass uncaught interface errors to Kite's own server. It forwards them to crash reporting only
 * when the user has turned that on, and strips anything personal first; otherwise they stop there.
 */
export function installCrashReports(): void {
    const reported = new Set<string>();

    const report = (message: string, stack?: string) => {
        if (reported.has(message) || reported.size >= MAX_REPORTS) {
            return;
        }

        reported.add(message);
        void requestJson(reportCrash(), {
            message: message.slice(0, 1000),
            stack: stack?.slice(0, 10000) ?? null,
        }).catch(() => undefined);
    };

    window.addEventListener('error', (event) => {
        const error: unknown = event.error;

        report(
            error instanceof Error ? error.message : event.message,
            error instanceof Error ? error.stack : undefined,
        );
    });

    window.addEventListener('unhandledrejection', (event) => {
        const reason: unknown = event.reason;

        report(
            reason instanceof Error ? reason.message : String(reason),
            reason instanceof Error ? reason.stack : undefined,
        );
    });
}
