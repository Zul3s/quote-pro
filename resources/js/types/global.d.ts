// Keep this file a module (not a global script) so the `declare module`
// blocks below are treated as augmentations, not ambient redeclarations.
export {};

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
