import { CheckCircle2, CircleDashed, Clock3, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const tones = {
    warning: {
        icon: Clock3,
        className: 'bg-junta-warning-soft text-junta-warning',
    },
    success: {
        icon: CheckCircle2,
        className: 'bg-junta-success-soft text-junta-success',
    },
    error: { icon: XCircle, className: 'bg-junta-error-soft text-junta-error' },
    neutral: {
        icon: CircleDashed,
        className: 'bg-muted text-muted-foreground',
    },
};

export function StatusLabel({
    children,
    tone = 'neutral',
}: {
    children: string;
    tone?: keyof typeof tones;
}) {
    const { icon: Icon, className } = tones[tone];
    return (
        <Badge
            className={cn(
                'junta-admin-status gap-1.5 border-transparent',
                className,
            )}
        >
            <Icon className="size-3.5 shrink-0" aria-hidden="true" />
            {children}
        </Badge>
    );
}
