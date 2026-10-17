import { toast } from 'sonner';
import { requestJson } from '@/lib/kite-http';
import { onNativeEvent } from '@/lib/native-events';
import { store as installUpdate } from '@/routes/app-update';
import { create as quickCapture } from '@/routes/capture';

const EVENTS = 'Native\\Desktop\\Events\\AutoUpdater\\';

/** Every update message shares one toast, so each step replaces the last. */
const TOAST = 'app-update';

type UpdateAvailable = { version: string };
type DownloadProgress = { percent: number };
type UpdateDownloaded = { version: string };

/**
 * Keep the user posted on an update: while a new version downloads (so closing Kite doesn't
 * look like nothing happened), and once it's ready, with a button to restart into it.
 * Otherwise it installs the next time Kite quits. The quick capture window stays out of it.
 */
export function installUpdateNotice(): void {
    if (window.location.pathname === quickCapture().url) {
        return;
    }

    let downloading: string | null = null;
    let shownPercent = -1;

    const showDownloading = (version: string, percent?: number) => {
        toast.loading(
            percent === undefined
                ? `Downloading Kite ${version}…`
                : `Downloading Kite ${version}… ${percent}%`,
            {
                id: TOAST,
                description: 'Keep Kite open until it’s ready.',
                duration: Infinity,
            },
        );
    };

    onNativeEvent<UpdateAvailable>(
        `${EVENTS}UpdateAvailable`,
        ({ version }) => {
            downloading = version;
            shownPercent = -1;
            showDownloading(version);
        },
    );

    onNativeEvent<DownloadProgress>(
        `${EVENTS}DownloadProgress`,
        ({ percent }) => {
            const rounded = Math.floor(percent);

            // Progress arrives many times a second; only redraw when the number changes.
            if (downloading === null || rounded === shownPercent) {
                return;
            }

            shownPercent = rounded;
            showDownloading(downloading, rounded);
        },
    );

    onNativeEvent<UpdateDownloaded>(
        `${EVENTS}UpdateDownloaded`,
        ({ version }) => {
            downloading = null;

            toast(`Kite ${version} is ready`, {
                id: TOAST,
                description: 'It will be installed when you quit Kite.',
                duration: Infinity,
                action: {
                    label: 'Restart now',
                    onClick: () =>
                        void requestJson(installUpdate()).catch(() =>
                            toast.error('Could not install the update.'),
                        ),
                },
            });
        },
    );

    // Errors outside a download (e.g. being offline when Kite checks) aren't worth a message.
    onNativeEvent(`${EVENTS}Error`, () => {
        if (downloading === null) {
            return;
        }

        toast.error(`Couldn’t download Kite ${downloading}`, {
            id: TOAST,
            description: 'Kite will try again the next time it starts.',
        });
        downloading = null;
    });
}
