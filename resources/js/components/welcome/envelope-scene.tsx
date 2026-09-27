import type { ReactNode } from 'react';
import {
    ENVELOPE_BODY,
    ENVELOPE_COLOURS,
    ENVELOPE_CREASE,
    ENVELOPE_FLAP,
    ENVELOPE_ISO,
} from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/** 0 is the logo itself; 1–5 follow the sections on the home page. */
export type SceneStep = 0 | 1 | 2 | 3 | 4 | 5;

type Pose = { x: number; y: number; s: number; o?: number };

// The logo's stack, blown up: 4.2 units between layers at scale 3.2.
const STACK = {
    sky: { x: 100, y: 122, s: 3.2 },
    violet: { x: 100, y: 108.6, s: 3.2 },
    pink: { x: 100, y: 95.2, s: 3.2 },
} satisfies Record<string, Pose>;

const POSES: Record<'sky' | 'violet' | 'pink', Record<SceneStep, Pose>> = {
    sky: {
        0: STACK.sky,
        1: { x: 46, y: 116, s: 2.2 },
        2: STACK.sky,
        3: STACK.sky,
        4: STACK.sky,
        5: STACK.sky,
    },
    violet: {
        0: STACK.violet,
        1: { x: 154, y: 116, s: 2.2 },
        2: STACK.violet,
        3: STACK.violet,
        4: STACK.violet,
        5: STACK.violet,
    },
    pink: {
        0: STACK.pink,
        1: { x: 100, y: 80, s: 2.6 },
        2: STACK.pink,
        3: STACK.pink,
        4: { x: 170, y: 44, s: 2.3 },
        5: STACK.pink,
    },
};

// The other businesses a search turns up; they come out of the stack and go back into it.
const LEADS: { colour: string; found: Pose }[] = [
    { colour: ENVELOPE_COLOURS.pink, found: { x: 36, y: 56, s: 1.5, o: 0.5 } },
    { colour: ENVELOPE_COLOURS.sky, found: { x: 164, y: 50, s: 1.4, o: 0.5 } },
    {
        colour: ENVELOPE_COLOURS.violet,
        found: { x: 70, y: 150, s: 1.3, o: 0.35 },
    },
    {
        colour: ENVELOPE_COLOURS.pink,
        found: { x: 134, y: 154, s: 1.5, o: 0.35 },
    },
    {
        colour: ENVELOPE_COLOURS.violet,
        found: { x: 100, y: 30, s: 1.2, o: 0.3 },
    },
];

const MOVE =
    'transition-[transform,opacity] duration-700 ease-[cubic-bezier(0.2,0.8,0.2,1)] motion-reduce:transition-none';

function place({ x, y, s, o = 1 }: Pose) {
    return {
        transform: `translate(${x}px, ${y}px) scale(${s})`,
        opacity: o,
    };
}

/** Positioned in scene space; everything inside is drawn around 0,0 in envelope units. */
function Posed({
    pose,
    className,
    children,
}: {
    pose: Pose;
    className?: string;
    children: ReactNode;
}) {
    return (
        <g className={cn(MOVE, className)} style={place(pose)}>
            {children}
        </g>
    );
}

function Card({ fill }: { fill: string }) {
    return (
        <g transform={ENVELOPE_ISO}>
            <rect {...ENVELOPE_BODY} fill={fill} />
        </g>
    );
}

export function EnvelopeScene({
    step,
    className,
}: {
    step: SceneStep;
    className?: string;
}) {
    const flapOpen = step === 2 || step === 3;
    const sheetOut = step === 2 || step === 3;

    return (
        <svg
            viewBox="0 0 200 170"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className={cn('overflow-visible', className)}
        >
            {/* A soft floor shadow under the stack, so it sits somewhere. */}
            <ellipse
                cx={100}
                cy={140}
                rx={46}
                ry={9}
                className={cn(
                    MOVE,
                    'fill-violet-500/15 blur-md dark:fill-violet-500/20',
                )}
                style={{ opacity: step === 1 ? 0 : 1 }}
            />

            {LEADS.map((lead, index) => (
                <Posed
                    key={index}
                    pose={
                        step === 1
                            ? lead.found
                            : { ...STACK.violet, s: 0.6, o: 0 }
                    }
                >
                    <Card fill={lead.colour} />
                </Posed>
            ))}

            <Posed pose={POSES.sky[step]}>
                <g className="envelope-bob">
                    <Card fill={ENVELOPE_COLOURS.sky} />
                </g>
            </Posed>

            <Posed pose={POSES.violet[step]}>
                <g className="envelope-bob [animation-delay:-2s]">
                    <Card fill={ENVELOPE_COLOURS.violet} />
                </g>
            </Posed>

            <Posed pose={POSES.pink[step]}>
                <g className="envelope-bob [animation-delay:-4s]">
                    {/* Speed lines trail behind it, along the way it flies off. */}
                    <path
                        d="M-24 2 h9 M-27 6.5 h7 M-22 -2.5 h6"
                        transform="rotate(-36)"
                        className={cn(MOVE, 'stroke-pink-400')}
                        strokeWidth={1.1}
                        strokeLinecap="round"
                        style={{ opacity: step === 4 ? 0.8 : 0 }}
                    />

                    <g transform={ENVELOPE_ISO}>
                        <rect {...ENVELOPE_BODY} fill={ENVELOPE_COLOURS.pink} />
                        {/* Opening mirrors the flap over the top edge (y = -6). */}
                        <g
                            className={MOVE}
                            style={{
                                transform: flapOpen
                                    ? 'translate(0px, -12px) scale(1, -1)'
                                    : 'none',
                            }}
                        >
                            <path
                                d={ENVELOPE_FLAP}
                                strokeWidth={1}
                                strokeLinejoin="round"
                                className="transition-[fill,stroke] duration-700"
                                style={{
                                    fill: flapOpen
                                        ? '#f9a8d4'
                                        : ENVELOPE_COLOURS.flap,
                                    stroke: flapOpen
                                        ? '#f9a8d4'
                                        : ENVELOPE_COLOURS.flap,
                                }}
                            />
                            <path
                                d={ENVELOPE_CREASE}
                                fill="none"
                                stroke={ENVELOPE_COLOURS.crease}
                                strokeWidth={0.6}
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </g>
                    </g>

                    {/* The reply badge. */}
                    <g
                        className={MOVE}
                        style={{
                            transform: `translate(11px, -6px) scale(${step === 5 ? 1 : 0})`,
                        }}
                    >
                        <circle
                            r={3.6}
                            className="fill-green-500 stroke-background"
                            strokeWidth={1}
                        />
                        <path
                            d="M-1.5 0.1 L-0.4 1.2 L1.6 -1"
                            fill="none"
                            stroke="#052e16"
                            strokeWidth={0.9}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </g>
                </g>
            </Posed>

            {/* The sheet: site text while reading, then the filled-in mail while writing. */}
            <Posed
                pose={
                    sheetOut
                        ? { x: 100, y: step === 3 ? 44 : 50, s: 2.9 }
                        : { ...STACK.pink, s: 2.2, o: 0 }
                }
            >
                <g transform={ENVELOPE_ISO}>
                    <rect
                        x={-7.5}
                        y={-5}
                        width={15}
                        height={10}
                        rx={1}
                        className="fill-neutral-50"
                    />
                    <g strokeLinecap="round" strokeWidth={0.9}>
                        <path d="M-5 -2.6 h10" className="stroke-neutral-300" />
                        <path
                            d="M-5 0 h6"
                            className={cn(MOVE, 'stroke-neutral-300')}
                            style={{ opacity: step === 3 ? 0 : 1 }}
                        />
                        <path d="M-5 2.6 h7.5" className="stroke-neutral-300" />
                    </g>
                    {/* {{hook}} lights up once Claude has filled it. */}
                    <rect
                        x={-5.4}
                        y={-1}
                        width={7.4}
                        height={2}
                        rx={1}
                        className={cn(MOVE, 'fill-violet-500')}
                        style={{ opacity: step === 3 ? 1 : 0 }}
                    />
                </g>
            </Posed>
        </svg>
    );
}
