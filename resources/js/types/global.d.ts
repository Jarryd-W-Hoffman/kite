import type { EditorPreferences, Preferences } from '@/types/kite';
import type { FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: {
            name: string;
            kite: {
                native: boolean;
                platform: string;
                editor: EditorPreferences;
                tree_group_colours: boolean;
            };
            preferences?: Preferences;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
