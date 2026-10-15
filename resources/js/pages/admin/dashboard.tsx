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
import {
    StatusDistributionBar,
    statusOrder,
} from '@/components/examis/status-distribution-bar';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useTrans } from '@/hooks/use-trans';
import admin from '@/routes/admin';
import type { ApplicationStatus } from '@/types';

type Props = {
    statusCounts: { status: ApplicationStatus; label: string; count: number }[];
    myQueue: number;
};

/**
 * Card colours. Each status has one hue, shared by its bar segment, its card
 * avatar and (lightly) its card background, matching the status badges.
 * Meaning is never carried by colour alone: every card also has an icon
 * shape and a text label.
 */
type Appearance = {
    /** Solid fill: bar segment and avatar background. */
    fill: string;
    /** Avatar icon colour and ring. */
    avatar: string;
    /**
     * Light card background: the hue mixed into the opaque card colour, so
     * the illustration behind never reduces text contrast.
     */
    tint: string;
};

/** Class names are written out in full so Tailwind can find them. */
const statusAppearance: Partial<
    Record<ApplicationStatus, { icon: LucideIcon } & Appearance>
> = {
    accepted: {
        icon: CheckCircle2,
        fill: 'bg-green-600',
        avatar: 'text-white ring-green-500/25',
        tint: 'bg-[color-mix(in_srgb,var(--color-green-500)_8%,var(--card))] border-green-500/25',
    },
    submitted: {
        icon: Send,
        fill: 'bg-blue-600',
        avatar: 'text-white ring-blue-500/25',
        tint: 'bg-[color-mix(in_srgb,var(--color-blue-500)_8%,var(--card))] border-blue-500/25',
    },
    under_review: {
        icon: Search,
        fill: 'bg-yellow-500',
        avatar: 'text-black/80 ring-yellow-500/30',
        tint: 'bg-[color-mix(in_srgb,var(--color-yellow-500)_10%,var(--card))] border-yellow-500/30',
    },
    requires_correction: {
        icon: FileEdit,
        fill: 'bg-orange-500',
        avatar: 'text-white ring-orange-500/25',
        tint: 'bg-[color-mix(in_srgb,var(--color-orange-500)_8%,var(--card))] border-orange-500/25',
    },
    resubmitted: {
        icon: RotateCcw,
        fill: 'bg-teal-600',
        avatar: 'text-white ring-teal-500/25',
        tint: 'bg-[color-mix(in_srgb,var(--color-teal-500)_8%,var(--card))] border-teal-500/25',
    },
    rejected: {
        icon: XCircle,
        fill: 'bg-red-600',
        avatar: 'text-white ring-red-500/25',
        tint: 'bg-[color-mix(in_srgb,var(--color-red-500)_8%,var(--card))] border-red-500/25',
    },
    withdrawn: {
        icon: CircleSlash,
        fill: 'bg-stone-500',
        avatar: 'text-white ring-stone-500/25',
        tint: 'bg-[color-mix(in_srgb,var(--color-stone-500)_8%,var(--card))] border-stone-500/25',
    },
};

/** Queue cards are not statuses, so they use the brand colours. */
const queueAppearance: Appearance = {
    fill: 'bg-primary dark:bg-(--examis-pearl)',
    avatar: 'text-primary-foreground ring-primary/25 dark:text-(--examis-navy) dark:ring-(--examis-pearl)/25',
    tint: 'bg-[color-mix(in_srgb,var(--primary)_8%,var(--card))] border-primary/20',
};

/** Distribution-bar colours: the same fill as each status card's avatar. */
const statusFill = Object.fromEntries(
    Object.entries(statusAppearance).map(([status, appearance]) => [
        status,
        appearance.fill,
    ]),
) as Partial<Record<ApplicationStatus, string>>;

type Card = {
    key: string;
    label: string;
    count: number;
    icon: LucideIcon;
    appearance: Appearance;
    href: ReturnType<typeof admin.applications.index>;
};

function StatCard({ card }: { card: Card }) {
    const Icon = card.icon;

    return (
        <Link
            href={card.href}
            className={`group flex w-[calc(50%-0.375rem)] flex-col items-center justify-center gap-1.5 rounded-lg border px-2 py-3 text-center shadow-sm transition-[translate,box-shadow] hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-[calc(25%-0.5625rem)] ${card.appearance.tint}`}
            data-test={`stat-${card.key}`}
        >
            <Avatar
                className={`size-10 ring-4 transition-transform group-hover:scale-105 ${card.appearance.fill} ${card.appearance.avatar}`}
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

    /** Status cards follow the bar's order; the queue cards come last. */
    const cards: Card[] = [
        ...statusOrder.flatMap((status) => {
            const item = statusCounts.find((row) => row.status === status);
            const appearance = statusAppearance[status];

            return item && appearance
                ? [
                      {
                          key: status,
                          label: item.label,
                          count: item.count,
                          icon: appearance.icon,
                          appearance,
                          href: admin.applications.index({
                              query: { status },
                          }),
                      },
                  ]
                : [];
        }),
        {
            key: 'awaiting',
            label: t('admin.awaiting_review'),
            count: awaiting,
            icon: ClipboardList,
            appearance: queueAppearance,
            href: admin.applications.index({ query: { status: 'submitted' } }),
        },
        {
            key: 'my-queue',
            label: t('admin.my_queue'),
            count: myQueue,
            icon: UserCheck,
            appearance: queueAppearance,
            href: admin.applications.index({
                query: { assigned: 'me', status: 'under_review' },
            }),
        },
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
                    {/* Sized to the one-line legend; the cards follow that width. */}
                    <div className="grid w-fit max-w-full gap-5">
                        <StatusDistributionBar
                            rows={statusCounts}
                            colors={statusFill}
                        />

                        {/* Four columns (two on phones); an incomplete last row is centred. */}
                        <div className="flex w-0 min-w-full flex-wrap justify-center gap-3">
                            {cards.map((card) => (
                                <StatCard key={card.key} card={card} />
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
