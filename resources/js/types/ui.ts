export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** A follow-up, such as Undo, that posts to `url`. */
    action?: { label: string; url: string };
};
