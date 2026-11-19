import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    badge?: ReactNode;
    description?: string;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({
    title,
    badge,
    description,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <header
            data-slot="page-header"
            className={cn(
                'mb-8 flex flex-col gap-5 break-words sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div data-slot="page-heading" className="min-w-0 flex-1 space-y-2">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight sm:text-[28px]">
                        {title}
                    </h1>
                    {badge}
                </div>
                {description && (
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div
                    data-slot="page-actions"
                    className="flex max-w-full flex-wrap items-center gap-2"
                >
                    {actions}
                </div>
            )}
        </header>
    );
}

export function Breadcrumbs({
    items,
}: {
    items: { label: string; href?: string }[];
}) {
    return (
        <nav aria-label="Migalhas de pão" className="consumer-breadcrumbs">
            {items.map((item, index) => (
                <span key={item.label}>
                    {index > 0 && <span aria-hidden="true"> / </span>}
                    {item.href ? (
                        <Link href={item.href}>{item.label}</Link>
                    ) : (
                        <span aria-current="page">{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}
