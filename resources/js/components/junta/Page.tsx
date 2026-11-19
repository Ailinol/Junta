import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { Breadcrumbs, PageHeader } from '@/components/junta/PageHeader';
import { AdminPageBody } from '@/components/junta/AdminPageBody';

export function Page({
    title,
    badge,
    description,
    actions,
    back,
    breadcrumbs,
    children,
    narrow = false,
    className,
}: {
    title: string;
    badge?: ReactNode;
    description?: string;
    actions?: ReactNode;
    back?: { href: string; label: string };
    breadcrumbs?: { label: string; href?: string }[];
    children: ReactNode;
    narrow?: boolean;
    className?: string;
}) {
    return (
        <>
            <Head title={title} />
            <main
                className={cn(
                    'junta-content mx-auto w-full px-4 py-7 pb-16 break-words sm:px-7 sm:py-9 lg:px-10',
                    narrow ? 'max-w-4xl' : 'max-w-7xl',
                    className,
                )}
            >
                {breadcrumbs && <Breadcrumbs items={breadcrumbs} />}
                {back && (
                    <Link
                        href={back.href}
                        className="mb-5 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-primary"
                    >
                        <ArrowLeft className="size-4" />
                        {back.label}
                    </Link>
                )}
                <PageHeader
                    title={title}
                    badge={badge}
                    description={description}
                    actions={actions}
                    className="junta-content-header"
                />
                <AdminPageBody>{children}</AdminPageBody>
            </main>
        </>
    );
}

export function DetailList({
    items,
}: {
    items: { label: string; value: ReactNode }[];
}) {
    return (
        <dl className="junta-fact-grid">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="min-w-0 rounded-xl border border-border bg-muted/40 p-4 text-sm"
                >
                    <dt className="text-muted-foreground">{item.label}</dt>
                    <dd className="mt-2 font-semibold break-words">
                        {item.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function Notice({ children }: { children: ReactNode }) {
    return (
        <div className="my-4 flex items-start gap-3 rounded-xl border border-primary/15 bg-junta-primary-soft px-4 py-4 text-sm leading-6 text-muted-foreground">
            <Info
                className="mt-0.5 size-4 shrink-0 text-primary"
                aria-hidden="true"
            />
            <div>{children}</div>
        </div>
    );
}
