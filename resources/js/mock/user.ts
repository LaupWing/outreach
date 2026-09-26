import type { User } from '@/types';

/** Stands in for the logged-in user until the pages get real auth props. */
export const mockUser: User = {
    id: 1,
    name: 'Loc Nguyen',
    email: 'loc@snelstack.com',
    email_verified_at: '2026-09-01T09:00:00.000000Z',
    created_at: '2026-09-01T09:00:00.000000Z',
    updated_at: '2026-09-01T09:00:00.000000Z',
};
