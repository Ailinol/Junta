import { Inbox } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { ComponentType, ReactElement } from 'react';
import { isValidElement } from 'react';

type EmptyStateProps = {
    icon?: ComponentType<{ className?: string }> | ReactElement;
    title: string;
    description?: string;
    actionLabel?: string;
    onAction?: () => void;
    action?: {
        label: string;
        onClick: () => void;
    };
    actionHref?: string;
    className?: string;
    tone?: 'brand' | 'success';
};

export function EmptyState({
    icon: IconComponent = Inbox,
    title,
    description,
    actionLabel,
    onAction,
    action,
    actionHref,
    className,
    tone = 'brand',
}: EmptyStateProps) {
    const finalActionLabel = action?.label || actionLabel;
    const finalOnAction = action?.onClick || onAction;

    return (
        <div
            data-color={tone}
            className={cn(
                'junta-empty-state',
                'flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-primary/25 bg-junta-primary-soft p-8 text-center',
                className,
            )}
        >
            {IconComponent && (
                <div className="junta-empty-icon flex size-11 items-center justify-center rounded-md bg-muted text-muted-foreground">
                    {isValidElement(IconComponent) ? (
                        IconComponent
                    ) : (
                        <IconComponent className="size-6" />
                    )}
                </div>
            )}
            <div className="space-y-1">
                <p className="text-sm font-semibold text-foreground">{title}</p>
                {description && (
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {finalActionLabel && actionHref && (
                <Button asChild className="mt-1">
                    <Link href={actionHref}>{finalActionLabel}</Link>
                </Button>
            )}
            {finalActionLabel && finalOnAction && !actionHref && (
                <Button onClick={finalOnAction} className="mt-1">
                    {finalActionLabel}
                </Button>
            )}
        </div>
    );
}
