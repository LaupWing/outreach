import type { Niche } from '@/types';

export const mockNiches: Niche[] = [
    {
        id: 1,
        name: 'Tandartsen',
        status: 'testing',
        why: 'Veel praktijken met verouderde sites en een vaste geldstroom.',
        findings:
            'Eerste batch van 20 mails: twee reacties, allebei op de hook over mobiel. De praktijkmanager beslist, niet de tandarts. Vaste prijs en "in twee weken live" doen het goed; een gratis check wordt genegeerd.',
    },
    {
        id: 2,
        name: 'Fysiotherapeuten',
        status: 'testing',
        why: 'Sterk lokaal, concurreren op vindbaarheid.',
        findings:
            'Veel praktijken zitten in een keten met een centrale site, dus filteren op zelfstandige praktijken. De snelheidscheck levert opens op maar nog geen reacties.',
    },
    {
        id: 3,
        name: 'Restaurants',
        status: 'idea',
        why: 'Groot volume, maar lage marges.',
        findings:
            'Nog niet gemaild. Eerste scrape laat zien dat de meeste zaken alleen Instagram en een Google-profiel hebben; de site is vaak van de franchise.',
    },
    {
        id: 4,
        name: 'Advocatenkantoren',
        status: 'idea',
        why: 'Hoge orderwaarde, sites vaak uit 2015.',
        findings:
            'Nog niet gemaild. Vrijwel elk kantoor heeft een info@-adres; de kans is groot dat de mail bij het secretariaat blijft hangen.',
    },
];
