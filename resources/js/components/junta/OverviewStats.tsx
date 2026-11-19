import type { LucideIcon } from 'lucide-react';

type OverviewStat = {
    label: string;
    value: number;
    description: string;
    icon: LucideIcon;
};

export function OverviewStats({ items }: { items: OverviewStat[] }) {
    return (
        <dl className="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
            {items.map(({ label, value, description, icon: Icon }) => (
                <div
                    key={label}
                    className="relative min-w-0 rounded-2xl border border-border bg-card p-4 sm:p-5"
                >
                    <dt className="flex min-h-8 items-start justify-between gap-2 text-xs font-medium text-muted-foreground sm:min-h-5 sm:text-[13px]">
                        {label}
                        <Icon
                            className="size-4 shrink-0 text-primary/70"
                            aria-hidden="true"
                        />
                    </dt>
                    <dd className="mt-4">
                        <span className="text-3xl leading-none font-semibold tracking-tight tabular-nums sm:text-[34px]">
                            {value}
                        </span>
                        <span className="mt-3 block text-[11px] leading-5 text-muted-foreground sm:text-xs">
                            {description}
                        </span>
                    </dd>
                </div>
            ))}
        </dl>
    );
}
