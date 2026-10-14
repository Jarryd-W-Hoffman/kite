import type { Auth } from '@/types/auth';
import type { EditorPreferences, Preferences } from '@/types/kite';
import type { FlashToast } from '@/types/ui';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            kite: {
                native: boolean;
                platform: string;
                editor: EditorPreferences;
            };
            preferences?: Preferences;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
