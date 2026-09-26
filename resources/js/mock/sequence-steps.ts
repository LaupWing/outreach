import type { SequenceStep } from '@/types';

export const mockSequenceSteps: SequenceStep[] = [
    // Offer 1: Nieuwe website in 2 weken (tandartsen)
    {
        id: 1,
        offer_id: 1,
        step: 1,
        days_after_previous: 0,
        subject: '{{hook_subject}}',
        body: 'Hoi,\n\n{{hook}}\n\nIk bouw praktijksites die in twee weken live staan, vaste prijs, met online afspraken. Zal ik laten zien hoe die van {{company}} eruit zou zien?\n\nLoc',
    },
    {
        id: 2,
        offer_id: 1,
        step: 2,
        days_after_previous: 4,
        subject: 'Re: {{hook_subject}}',
        body: 'Hoi,\n\nNog even hierop terugkomen. Ik heb een schets gemaakt van hoe {{company}} er op mobiel uit zou zien. Zal ik hem sturen?\n\nLoc',
    },
    {
        id: 3,
        offer_id: 1,
        step: 3,
        days_after_previous: 7,
        subject: 'Re: {{hook_subject}}',
        body: 'Hoi,\n\nLaatste keer dat ik stoor. Als het nu niet uitkomt, prima. Mocht de site later aan de beurt zijn, dan weet je me te vinden.\n\nLoc',
    },
    // Offer 2: Gratis snelheidscheck (fysio)
    {
        id: 4,
        offer_id: 2,
        step: 1,
        days_after_previous: 0,
        subject: '{{hook_subject}}',
        body: 'Hoi,\n\n{{hook}}\n\nIk doe gratis een check van laadtijd en mobiel gebruik voor {{company}}, met een kort rapport. Zal ik hem sturen?\n\nLoc',
    },
    {
        id: 5,
        offer_id: 2,
        step: 2,
        days_after_previous: 5,
        subject: 'Re: {{hook_subject}}',
        body: 'Hoi,\n\nHet rapport staat klaar, het kost jullie niks. Zal ik het sturen?\n\nLoc',
    },
];
