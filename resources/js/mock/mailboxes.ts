import type { Mailbox } from '@/types';

export const mockMailboxes: Mailbox[] = [
    {
        id: 1,
        address: 'loc@snelstack.com',
        type: 'gmail',
        daily_limit: 40,
        sent_today: 12,
        status: 'active',
    },
    {
        id: 2,
        address: 'loc@snelstack.nl',
        type: 'gmail',
        daily_limit: 20,
        sent_today: 3,
        status: 'warming_up',
    },
];
