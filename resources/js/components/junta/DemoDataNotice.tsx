import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { usePage } from '@inertiajs/react';

type DemoDataNoticeProps = {
    children?: ReactNode;
};

export function DemoDataNotice({ children }: DemoDataNoticeProps) {
    const { business } = usePage().props;
    if ((business as { isDemo?: boolean } | undefined)?.isDemo === false)
        return null;
    return (
        <div
            role="status"
            className="flex items-center gap-2 border-b border-border bg-muted px-5 py-2.5 text-xs text-muted-foreground sm:px-8"
        >
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                {children ??
                    'Demonstração · Dados de exemplo. As alterações ficam apenas neste navegador.'}
            </p>
        </div>
    );
}
