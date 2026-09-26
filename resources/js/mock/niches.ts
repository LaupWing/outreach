import type { Niche } from '@/types';

export const mockNiches: Niche[] = [
    {
        id: 1,
        name: 'Tandartsen',
        status: 'testing',
        why: 'Veel praktijken met verouderde sites en een vaste geldstroom.',
        findings: null,
    },
    {
        id: 2,
        name: 'Fysiotherapeuten',
        status: 'testing',
        why: 'Sterk lokaal, concurreren op vindbaarheid.',
        findings: null,
    },
    {
        id: 3,
        name: 'Restaurants',
        status: 'idea',
        why: 'Groot volume, maar lage marges.',
        findings: null,
    },
    {
        id: 4,
        name: 'Advocatenkantoren',
        status: 'idea',
        why: 'Hoge orderwaarde, sites vaak uit 2015.',
        findings: null,
    },
];
