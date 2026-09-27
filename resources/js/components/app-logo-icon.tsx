import type { SVGAttributes } from 'react';

/**
 * The snelstack stack, with every layer turned into an envelope: sky, violet and pink, the
 * same three colours as the stack on snelstack.com. Each envelope is an 18×12 card lying flat,
 * rotated 45° and squashed to 62%, which is the snelstack construction.
 */
export const ENVELOPE_COLOURS = {
    sky: '#38bdf8',
    violet: '#a78bfa',
    pink: '#f472b6',
    flap: '#db2777',
    crease: '#fce7f3',
} as const;

export const ENVELOPE_ISO = 'scale(1 0.62) rotate(45)';

/** Local envelope shapes, drawn around 0,0 before the iso transform. */
export const ENVELOPE_BODY = { x: -9, y: -6, width: 18, height: 12, rx: 1.8 };
export const ENVELOPE_FLAP = 'M-8.4 -5.4 L0 0.6 L8.4 -5.4 Z';
export const ENVELOPE_CREASE = 'M-8.1 -5.1 L0 0.6 L8.1 -5.1';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect
                {...ENVELOPE_BODY}
                transform={`translate(12 16.2) ${ENVELOPE_ISO}`}
                fill={ENVELOPE_COLOURS.sky}
            />
            <rect
                {...ENVELOPE_BODY}
                transform={`translate(12 12) ${ENVELOPE_ISO}`}
                fill={ENVELOPE_COLOURS.violet}
            />
            <g transform={`translate(12 7.8) ${ENVELOPE_ISO}`}>
                <rect {...ENVELOPE_BODY} fill={ENVELOPE_COLOURS.pink} />
                <path
                    d={ENVELOPE_FLAP}
                    fill={ENVELOPE_COLOURS.flap}
                    stroke={ENVELOPE_COLOURS.flap}
                    strokeWidth={1}
                    strokeLinejoin="round"
                />
                <path
                    d={ENVELOPE_CREASE}
                    fill="none"
                    stroke={ENVELOPE_COLOURS.crease}
                    strokeWidth={0.8}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </g>
        </svg>
    );
}
