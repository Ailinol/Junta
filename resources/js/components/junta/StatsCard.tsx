import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { ComponentType, ReactElement } from 'react';
import { isValidElement } from 'react';

type StatsCardProps = {
    title?: string;
    label?: string;
    value: string | number;
    description?: string;
    icon?: ComponentType<{ className?: string }> | ReactElement;
    /** Positive or negative change, e.g. "+5%" or "-2" */
    change?: string;
    /** Whether the change is positive (green) or negative (red) */
    changePositive?: boolean;
    className?: string;
};

export function StatsCard({
    title,
    label,
    value,
    description,
    icon: IconComponent,
    change,
    changePositive,
    className,
}: StatsCardProps) {
    const displayLabel = title || label;

    return (
        <Card className={cn('gap-3 py-4 shadow-xs', className)}>
            <CardContent className="px-5">
                <div className="flex items-start justify-between gap-2">
                    <div className="space-y-1">
                        {displayLabel && (
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {displayLabel}
                            </p>
                        )}
                        <p className="text-2xl font-semibold text-foreground tabular-nums">
                            {value}
                        </p>
                        {description && (
                            <p className="text-xs text-muted-foreground">
                                {description}
                            </p>
                        )}
                        {change !== undefined && (
                            <p
                                className={cn(
                                    'text-xs font-medium',
                                    changePositive === undefined
                                        ? 'text-muted-foreground'
                                        : changePositive
                                          ? 'text-[var(--color-junta-success)]'
                                          : 'text-destructive',
                                )}
                            >
                                {change}
                            </p>
                        )}
                    </div>
                    {IconComponent && (
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            {isValidElement(IconComponent) ? (
                                IconComponent
                            ) : (
                                <IconComponent className="size-5" />
                            )}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
