import { progressPercent } from '@/lib/formatters';
import { cn } from '@/lib/utils';

type ProgressBarProps = {
    current: number;
    target: number;
    showLabel?: boolean;
    className?: string;
};

export function ProgressBar({
    current,
    target,
    showLabel = true,
    className,
}: ProgressBarProps) {
    const percent = progressPercent(current, target);

    const barColor = 'bg-primary';

    return (
        <div className={cn('w-full', className)}>
            <div className="mb-1 flex items-center justify-between">
                <span className="text-xs text-muted-foreground">
                    Procura: {current.toLocaleString('pt-MZ')} /{' '}
                    {target.toLocaleString('pt-MZ')}
                </span>
                {showLabel && (
                    <span className="text-xs font-semibold tabular-nums">
                        {percent}%
                    </span>
                )}
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-secondary">
                <div
                    className={cn(
                        'h-full rounded-full transition-all duration-500',
                        barColor,
                    )}
                    style={{ width: `${percent}%` }}
                    role="progressbar"
                    aria-label="Procura reunida face ao objectivo"
                    aria-valuenow={percent}
                    aria-valuemin={0}
                    aria-valuemax={100}
                />
            </div>
        </div>
    );
}
