import { campaignUnit } from '@/lib/campaign-presentation';
import { formatMZN, progressPercent } from '@/lib/formatters';
import { cn } from '@/lib/utils';
export function ReferencePrice({
    price,
    unit,
    compact = false,
}: {
    price?: number;
    unit: string;
    compact?: boolean;
}) {
    return (
        <div>
            <p className="text-xs font-medium text-muted-foreground">
                Preço estimado · referência
            </p>
            <p
                className={cn(
                    'mt-1 flex flex-wrap items-baseline gap-x-1 font-semibold tracking-tight text-foreground',
                    compact ? 'text-2xl' : 'text-3xl',
                )}
            >
                {price !== undefined ? (
                    <>
                        {formatMZN(price)}
                        <span className="text-base font-normal tracking-normal text-muted-foreground">
                            /{campaignUnit(unit)}
                        </span>
                    </>
                ) : (
                    'Não informado'
                )}
            </p>
            {!compact && (
                <p className="mt-1 text-sm text-muted-foreground">
                    Estimativa. O preço final depende das propostas.
                </p>
            )}
        </div>
    );
}
export function DemandMeter({
    current,
    target,
    unit,
    showRemaining = false,
    showLabel = true,
    metricLabel = 'Interesse reunido',
}: {
    current: number;
    target: number;
    unit: string;
    showRemaining?: boolean;
    showLabel?: boolean;
    metricLabel?: string;
}) {
    const remaining = Math.max(0, target - current);
    return (
        <div>
            {showLabel && (
                <div className="mb-2 flex flex-wrap items-baseline justify-between gap-1 text-sm">
                    <span>
                        <strong className="font-semibold text-foreground">
                            {current.toLocaleString('pt-MZ')}
                        </strong>{' '}
                        <span className="text-muted-foreground">
                            de {target.toLocaleString('pt-MZ')} {unit}
                        </span>
                    </span>
                </div>
            )}
            {target > 0 && (
                <div
                    role="progressbar"
                    aria-label={metricLabel}
                    aria-valuemin={0}
                    aria-valuemax={target}
                    aria-valuenow={Math.min(current, target)}
                    aria-valuetext={current + ' de ' + target + ' ' + unit}
                    className="h-1.5 overflow-hidden rounded-full bg-border"
                >
                    <div
                        className="h-full rounded-full bg-primary"
                        style={{
                            width: progressPercent(current, target) + '%',
                        }}
                    />
                </div>
            )}
            {showRemaining && target > 0 && (
                <p className="mt-2 text-sm text-muted-foreground">
                    {remaining > 0 ? (
                        <>
                            Faltam{' '}
                            <strong className="font-medium text-foreground">
                                {remaining.toLocaleString('pt-MZ')}{' '}
                                {campaignUnit(unit, remaining)}
                            </strong>{' '}
                            para o objectivo.
                        </>
                    ) : (
                        'Objectivo de procura reunido.'
                    )}
                </p>
            )}
        </div>
    );
}
