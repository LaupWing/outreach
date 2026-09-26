import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /** Shown in front of the title in the page header, like the sidebar icon of the page. */
    icon?: LucideIcon;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type NavGroup = {
    label?: string;
    /** Items without an href or onClick are planned pages and render disabled. */
    items: (Omit<NavItem, 'href'> & {
        href?: NavItem['href'];
        /** Does something instead of going somewhere, like opening the search. */
        onClick?: () => void;
        /** Keyboard hint shown on the right, like ⌘K. */
        hint?: string;
    })[];
};
