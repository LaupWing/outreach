import type { Lead } from '@/types';

/** Matches one {{tag}}; the name is lowercase letters, digits and underscores. */
const TAG = /\{\{\s*([a-z0-9_]+)\s*\}\}/gi;

/** Tags every lead fills from its own columns; the rest come from `lead.facts`. */
export const builtInTags = [
    'company',
    'email',
    'phone',
    'website',
    'city',
    'hook',
    'hook_subject',
] as const;

export const isBuiltIn = (tag: string): boolean =>
    (builtInTags as readonly string[]).includes(tag);

/** The tags a text uses, unique and in order of first appearance. */
export function tagsIn(text: string): string[] {
    const tags: string[] = [];

    for (const match of text.matchAll(TAG)) {
        const tag = match[1].toLowerCase();

        if (!tags.includes(tag)) {
            tags.push(tag);
        }
    }

    return tags;
}

/** Everything a lead can fill in: the built-in fields, with its facts on top. */
export function valuesFor(lead: Lead): Record<string, string> {
    const hookSubject = lead.hook
        ? lead.hook.replace(/\.$/, '').slice(0, 60)
        : `Jullie website, ${lead.company}`;

    return {
        company: lead.company,
        email: lead.email ?? '',
        phone: lead.phone ?? '',
        website: lead.website ?? '',
        city: lead.city ?? '',
        hook: lead.hook ?? '',
        hook_subject: hookSubject,
        ...lead.facts,
    };
}

/** The tags in the text this lead has no value for yet. */
export function missing(text: string, lead: Lead): string[] {
    const values = valuesFor(lead);

    return tagsIn(text).filter((tag) => (values[tag] ?? '').trim() === '');
}

/** Fills the tags from the lead; unknown ones stay as {{tag}} so they stand out. */
export function fill(text: string, lead: Lead): string {
    const values = valuesFor(lead);

    return text.replace(TAG, (whole, name: string) => {
        const value = values[name.toLowerCase()];

        return value !== undefined && value.trim() !== '' ? value : whole;
    });
}
