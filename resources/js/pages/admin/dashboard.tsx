import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleSlash,
    ClipboardList,
    FileEdit,
    RotateCcw,
    Search,
    Send,
    UserCheck,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';
import type { ApplicationStatus } from '@/types';

type Props = {
    statusCounts: { status: ApplicationStatus; label: string; count: number }[];
    myQueue: number;
};

type Tone =
    | 'primary'
    | 'info'
    | 'warning'
    | 'success'
    | 'destructive'
    | 'muted';

/**
 * Avatar colours come from the theme tokens, so they follow the palette in
 * light and dark mode. Meaning is never carried by colour alone: every
 * card also has an icon shape and a text label.
 */
const toneClasses: Record<Tone, string> = {
    primary:
        'bg-primary text-primary-foreground ring-primary/25 dark:bg-(--examis-pearl) dark:text-(--examis-navy) dark:ring-(--examis-pearl)/25',
    info: 'bg-info text-white ring-info/25 dark:bg-[color-mix(in_srgb,var(--examis-green)_55%,var(--examis-pearl))] dark:ring-info/30',
    warning: 'bg-warning text-black/80 ring-warning/30',
    success: 'bg-success text-white ring-success/25',
    destructive: 'bg-destructive text-white ring-destructive/25',
    muted: 'bg-muted-foreground text-background ring-muted-foreground/25',
};

/**
 * Light card backgrounds: the tone mixed into the opaque card colour, so the
 * illustration behind never reduces text contrast.
 */
const cardTintClasses: Record<Tone, string> = {
    primary:
        'bg-[color-mix(in_srgb,var(--primary)_9%,var(--card))] border-primary/20',
    info: 'bg-[color-mix(in_srgb,var(--info)_11%,var(--card))] border-info/25',
    warning:
        'bg-[color-mix(in_srgb,var(--warning)_18%,var(--card))] border-warning/35',
    success:
        'bg-[color-mix(in_srgb,var(--success)_11%,var(--card))] border-success/25',
    destructive:
        'bg-[color-mix(in_srgb,var(--destructive)_10%,var(--card))] border-destructive/25',
    muted: 'bg-[color-mix(in_srgb,var(--muted-foreground)_9%,var(--card))] border-muted-foreground/20',
};

const statusAppearance: Partial<
    Record<ApplicationStatus, { icon: LucideIcon; tone: Tone }>
> = {
    submitted: { icon: Send, tone: 'info' },
    under_review: { icon: Search, tone: 'primary' },
    requires_correction: { icon: FileEdit, tone: 'warning' },
    resubmitted: { icon: RotateCcw, tone: 'info' },
    accepted: { icon: CheckCircle2, tone: 'success' },
    rejected: { icon: XCircle, tone: 'destructive' },
    withdrawn: { icon: CircleSlash, tone: 'muted' },
};

type Card = {
    key: string;
    label: string;
    count: number;
    icon: LucideIcon;
    tone: Tone;
    href: ReturnType<typeof admin.applications.index>;
};

function StatCard({ card }: { card: Card }) {
    const Icon = card.icon;

    return (
        <Link
            href={card.href}
            className={`group flex w-[calc(50%-0.375rem)] flex-col items-center justify-center gap-1.5 rounded-lg border px-2 py-3 text-center shadow-sm transition-[translate,box-shadow] hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-[calc(25%-0.5625rem)] ${cardTintClasses[card.tone]}`}
            data-test={`stat-${card.key}`}
        >
            <Avatar
                className={`size-10 ring-4 transition-transform group-hover:scale-105 ${toneClasses[card.tone]}`}
                aria-hidden
            >
                <AvatarFallback className="bg-transparent text-inherit">
                    <Icon className="size-5" strokeWidth={2} />
                </AvatarFallback>
            </Avatar>
            <span className="text-xs leading-snug font-medium text-muted-foreground">
                {card.label}
            </span>
            <span className="text-xl leading-none font-bold tabular-nums">
                {card.count}
            </span>
        </Link>
    );
}

export default function AdminDashboard({ statusCounts, myQueue }: Props) {
    const { t } = useTrans();

    setLayoutProps({
        breadcrumbs: [
            { title: t('nav.admin_dashboard'), href: admin.dashboard() },
        ],
    });

    const awaiting = statusCounts
        .filter(
            (item) =>
                item.status === 'submitted' || item.status === 'resubmitted',
        )
        .reduce((sum, item) => sum + item.count, 0);

    const cards: Card[] = [
        {
            key: 'awaiting',
            label: t('admin.awaiting_review'),
            count: awaiting,
            icon: ClipboardList,
            tone: 'primary',
            href: admin.applications.index({ query: { status: 'submitted' } }),
        },
        {
            key: 'my-queue',
            label: t('admin.my_queue'),
            count: myQueue,
            icon: UserCheck,
            tone: 'warning',
            href: admin.applications.index({
                query: { assigned: 'me', status: 'under_review' },
            }),
        },
        ...statusCounts.flatMap((item) => {
            const appearance = statusAppearance[item.status];

            return appearance
                ? [
                      {
                          key: item.status,
                          label: item.label,
                          count: item.count,
                          icon: appearance.icon,
                          tone: appearance.tone,
                          href: admin.applications.index({
                              query: { status: item.status },
                          }),
                      },
                  ]
                : [];
        }),
    ];

    return (
        <>
            <Head title={t('nav.admin_dashboard')} />

            {/* Fills the content area below the header bar, without page scrolling. */}
            <div className="flex flex-[1_0_auto] flex-col rounded-b-xl">
                <h1 className="sr-only">{t('admin.dashboard_title')}</h1>

                <section
                    aria-label={t('admin.by_status')}
                    className="flex flex-1 items-center justify-center p-4 sm:p-6"
                >
                    {/* Four columns (two on phones); an incomplete last row is centred. */}
                    <div className="flex w-full max-w-3xl flex-wrap justify-center gap-3">
                        {cards.map((card) => (
                            <StatCard key={card.key} card={card} />
                        ))}
                    </div>
                </section>
            </div>
        </>
    );
}
