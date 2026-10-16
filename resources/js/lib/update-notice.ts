import { toast } from 'sonner';
import { requestJson } from '@/lib/kite-http';
import { onNativeEvent } from '@/lib/native-events';
import { store as installUpdate } from '@/routes/app-update';
import { create as quickCapture } from '@/routes/capture';

type UpdateDownloaded = { version: string };

/**
 * Tell the user when a new version of Kite has downloaded, with a button to restart into it.
 * Otherwise it installs the next time Kite quits. The quick capture window stays out of it.
 */
export function installUpdateNotice(): void {
    if (window.location.pathname === quickCapture().url) {
        return;
    }

    onNativeEvent<UpdateDownloaded>(
        'Native\\Desktop\\Events\\AutoUpdater\\UpdateDownloaded',
        ({ version }) => {
            toast(`Kite ${version} is ready`, {
                id: 'app-update',
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
}
