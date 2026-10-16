import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { requestJson } from '@/lib/kite-http';
import { store as openExternal } from '@/routes/external-links';

/**
 * Opens a web link in the system browser. The desktop app asks the OS to do it, since a new
 * window there would be a bare app window with no address bar or back button.
 */
export function useOpenExternalLink() {
    const { kite } = usePage().props;

    return (url: string) => {
        if (!kite.native) {
            window.open(url, '_blank', 'noopener,noreferrer');

            return;
        }

        void requestJson(openExternal(), { url }).catch(() =>
            toast.error('Could not open the link.'),
        );
    };
}
