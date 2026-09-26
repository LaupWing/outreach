import { useSyncExternalStore } from 'react';

// A tiny store, so the sidebar item and the ⌘K shortcut open the same overlay.
let open = false;
const listeners = new Set<() => void>();

export function setSearchOpen(value: boolean): void {
    open = value;
    listeners.forEach((listener) => listener());
}

export function useSearchOpen(): boolean {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        () => open,
        () => false,
    );
}
