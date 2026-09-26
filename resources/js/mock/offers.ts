import type { Offer } from '@/types';

export const mockOffers: Offer[] = [
    {
        id: 1,
        name: 'Nieuwe website in 2 weken',
        niche_id: 1,
        description:
            'Snelle, mobiele praktijksite met online afspraken. Vaste prijs.',
        status: 'active',
    },
    {
        id: 2,
        name: 'Gratis snelheidscheck',
        niche_id: 2,
        description:
            'Rapport over laadtijd en mobiel gebruik, met een aanbod erachter.',
        status: 'active',
    },
    {
        id: 3,
        name: 'Menukaart online + reserveren',
        niche_id: 3,
        description: null,
        status: 'idea',
    },
];
