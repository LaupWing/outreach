import { useSyncExternalStore } from 'react';

// A tiny store, so the user menu, the sidebar and a shortcut open the same dialog.
let isOpen = false;
const listeners = new Set<() => void>();

export function setSettingsOpen(open: boolean): void {
    isOpen = open;
    listeners.forEach((listener) => listener());
}

export function useSettingsOpen(): boolean {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        () => isOpen,
        () => false,
    );
}
