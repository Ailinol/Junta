import { Link } from '@inertiajs/react';

export type DashboardBarChartItem = {
    label: string;
    value: number;
    detail: string;
    href?: string;
};

type DashboardBarChartProps = {
    ariaLabel: string;
    items: DashboardBarChartItem[];
    maxValue: number;
};

export function DashboardBarChart({
    ariaLabel,
    items,
    maxValue,
}: DashboardBarChartProps) {
    const chartMaximum = Math.max(maxValue, 1);

    return (
        <ul aria-label={ariaLabel} className="space-y-5">
            {items.map((item) => {
                const value = Math.max(0, item.value);
                const percentage = Math.min(100, (value / chartMaximum) * 100);
                const accessibleValue = Math.min(value, chartMaximum);

                return (
                    <li key={item.label} className="space-y-2">
                        <div className="flex min-w-0 flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                            {item.href ? (
                                <Link
                                    href={item.href}
                                    className="min-w-0 truncate text-sm font-medium text-foreground hover:text-junta-primary focus-visible:outline-junta-primary"
                                >
                                    {item.label}
                                </Link>
                            ) : (
                                <span className="min-w-0 truncate text-sm font-medium text-foreground">
                                    {item.label}
                                </span>
                            )}
                            <span className="min-w-0 text-xs break-words text-muted-foreground tabular-nums sm:shrink-0 sm:text-right">
                                {item.detail}
                            </span>
                        </div>
                        <div
                            role="progressbar"
                            aria-label={item.label}
                            aria-valuemin={0}
                            aria-valuemax={chartMaximum}
                            aria-valuenow={accessibleValue}
                            className="h-2 overflow-hidden rounded-full bg-junta-surface-muted"
                        >
                            <div
                                className="h-full rounded-full bg-junta-primary transition-[width]"
                                style={{ width: `${percentage}%` }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
