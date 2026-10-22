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
                /** Each menu command's shortcut, e.g. `CmdOrCtrl+K`; empty when it has none. */
                key_bindings: Record<string, string>;
                default_key_bindings: Record<string, string>;
            };
            preferences?: Preferences;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
