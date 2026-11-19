import type { OrganizerColor } from '@/lib/organizer-colors';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

type OperationalPanelProps = {
    title: string;
    description?: string;
    action?: { label: string; href: string };
    children: ReactNode;
    className?: string;
    emphasis?: OrganizerColor;
};

/** A compact container for a concrete task, list or decision. */
export function OperationalPanel({
    title,
    description,
    action,
    children,
    className,
    emphasis,
}: OperationalPanelProps) {
    return (
        <section
            data-emphasis={emphasis ? true : undefined}
            data-color={emphasis}
            className={cn(
                'junta-panel min-w-0 overflow-hidden rounded-2xl border border-border bg-card',
                className,
            )}
        >
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-5 py-5 sm:px-6">
                <div>
                    <h2 className="text-base font-semibold tracking-tight text-foreground">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1 text-sm text-[var(--color-junta-muted-foreground)]">
                            {description}
                        </p>
                    )}
                </div>
                {action && (
                    <Link
                        href={action.href}
                        className="junta-link inline-flex items-center gap-1 text-sm font-semibold text-[var(--color-junta-primary)]"
                    >
                        {action.label}
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                )}
            </header>
            <div className="junta-panel-body p-5 sm:p-6">{children}</div>
        </section>
    );
}
