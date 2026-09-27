export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    hasGoogleKey: boolean;
    mailboxCount: number;
    /** When queued mail may leave, per account. */
    sending: {
        send_timezone: string;
        send_from: number;
        send_until: number;
        send_weekdays_only: boolean;
    };
};
