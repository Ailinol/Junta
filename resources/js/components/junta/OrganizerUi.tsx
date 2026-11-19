import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import {
    ArrowRight,
    Boxes,
    CheckCircle2,
    FileText,
    Megaphone,
    PackageCheck,
    RotateCcw,
    Target,
    Users,
    Wallet,
    Check,
    CircleAlert,
    Clock3,
    FlaskConical,
    Inbox,
    LoaderCircle,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Page } from '@/components/junta/Page';
import { EmptyState } from '@/components/junta/EmptyState';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { Skeleton } from '@/components/ui/skeleton';
import { progressValue, maputoDate } from '@/lib/organizer-presentation';
import {
    organizerProgressColor,
    organizerStateColor,
    type OrganizerColor,
} from '@/lib/organizer-colors';
import { organizerActionUrl } from '@/lib/organizer-routes';
import type {
    Deadline,
    OrganizerCampaign,
    VisibleState,
} from '@/types/organizer';

export function PageShell(props: Parameters<typeof Page>[0]) {
    return (
        <Page {...props}>
            <OrganizerPageBody>{props.children}</OrganizerPageBody>
        </Page>
    );
}
export function OrganizerPageBody({ children }: { children: ReactNode }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const reading = useRef(false);
    useEffect(() => {
        const cleanups = [
            router.on('start', (event) => {
                if (event.detail.visit.method !== 'get') return;
                reading.current = true;
                setError('');
                if (!event.detail.visit.async) setLoading(true);
            }),
            router.on('finish', (event) => {
                if (event.detail.visit.method === 'get') {
                    reading.current = false;
                    setLoading(false);
                }
            }),
            router.on('networkError', (event) => {
                if (!reading.current) return;
                event.preventDefault();
                setLoading(false);
                setError(
                    'Não foi possível carregar a informação. Verifique a ligação e tente de novo.',
                );
            }),
            router.on('httpException', (event) => {
                if (!reading.current) return;
                event.preventDefault();
                setLoading(false);
                setError(
                    'Não foi possível carregar a página. Os dados anteriores continuam disponíveis.',
                );
            }),
        ];
        return () => cleanups.forEach((cleanup) => cleanup());
    }, []);
    return (
        <>
            {error && (
                <div className="organizer-alert" role="alert">
                    <CircleAlert
                        className="size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <p className="flex-1">{error}</p>
                    <button
                        className="junta-btn junta-btn-sm junta-btn-outline"
                        onClick={() => router.reload()}
                    >
                        Tentar de novo
                    </button>
                </div>
            )}
            {loading && (
                <div role="status" aria-live="polite" className="grid gap-6">
                    <span className="flex items-center gap-2 text-muted-foreground">
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        A carregar…
                    </span>
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {[0, 1, 2, 3].map((key) => (
                            <Skeleton key={key} className="h-24 rounded-xl" />
                        ))}
                    </div>
                    <Skeleton className="h-64 rounded-xl" />
                </div>
            )}
            <div
                hidden={loading}
                aria-busy={loading}
                className="organizer-page-body"
            >
                {children}
            </div>
        </>
    );
}
export function TestPaymentBanner({ visible }: { visible: boolean }) {
    return visible ? (
        <div className="organizer-test-banner">
            <FlaskConical className="size-4 shrink-0" aria-hidden="true" />
            Pagamento de teste · sem dinheiro real
        </div>
    ) : null;
}
export function NextStepBanner({
    view,
    showAction = true,
}: {
    view: OrganizerCampaign;
    showAction?: boolean;
}) {
    const state = view.state;
    if (state.isFinal || (!state.primaryAction && !state.waitingOn))
        return null;
    return (
        <section
            className="organizer-next-step"
            data-waiting={!state.primaryAction || undefined}
            data-color={
                state.blocker || view.deadline?.isPast
                    ? 'warning'
                    : state.waitingOn
                      ? 'info'
                      : 'brand'
            }
            aria-label={state.primaryAction ? 'Próximo passo' : 'A aguardar'}
        >
            <span className="organizer-next-icon">
                <Clock3 className="size-5" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="organizer-caption">
                    {state.primaryAction
                        ? 'O que precisa de fazer'
                        : 'Quem tem de agir'}
                </p>
                <h2>{state.primaryAction?.label ?? state.waitingOn?.label}</h2>
                {state.blocker && (
                    <p className="mt-2 font-medium">{state.blocker}</p>
                )}
                <p className="mt-2 text-muted-foreground">
                    {state.description}
                </p>
                {state.waitingOn?.since && (
                    <p className="mt-2 text-xs text-muted-foreground">
                        Desde {maputoDate(state.waitingOn.since)} · Maputo
                    </p>
                )}
            </div>
            {showAction && state.primaryAction && (
                <Link
                    className="junta-btn junta-btn-md junta-btn-primary"
                    href={organizerActionUrl(
                        view.campaign.id,
                        state.primaryAction,
                    )}
                >
                    {state.primaryAction.label}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            )}
        </section>
    );
}
const steps = [
    'Preparação',
    'Interesse',
    'Avaliação',
    'Selecção',
    'Pagamentos',
    'Mercadoria',
    'Conclusão',
];
export function PhaseStepper({ state }: { state: VisibleState }) {
    const current = state.step ?? 0;
    return (
        <div
            className="organizer-phase-stepper"
            aria-label="Etapas da campanha"
        >
            <p className="md:hidden">
                {current < 0
                    ? 'Percurso encerrado'
                    : `Etapa ${current + 1} de 7 · ${steps[current]}`}
            </p>
            <ol className="hidden md:flex">
                {steps.map((label, index) => (
                    <li
                        key={label}
                        aria-current={index === current ? 'step' : undefined}
                        data-done={index < current || undefined}
                    >
                        <span>
                            {index < current ? (
                                <Check
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            ) : (
                                index + 1
                            )}
                        </span>
                        <span>{label}</span>
                    </li>
                ))}
            </ol>
        </div>
    );
}
type KpiItem = {
    label: string;
    value: ReactNode;
    hint?: string;
    tone?: OrganizerColor;
    icon?:
        | 'campaign'
        | 'users'
        | 'units'
        | 'money'
        | 'proposal'
        | 'delivery'
        | 'target'
        | 'refund';
};
export function KpiStrip({ items }: { items: KpiItem[] }) {
    const icons = {
        campaign: Megaphone,
        users: Users,
        units: Boxes,
        money: Wallet,
        proposal: FileText,
        delivery: PackageCheck,
        target: Target,
        refund: RotateCcw,
    };
    return (
        <dl className="organizer-kpis">
            {items.slice(0, 4).map((item) => {
                const Icon = icons[item.icon ?? 'units'];
                return (
                    <div
                        key={item.label}
                        className="organizer-kpi"
                        data-color={item.tone ?? 'brand'}
                    >
                        <dt>
                            <span className="organizer-kpi-icon">
                                <Icon className="size-5" aria-hidden="true" />
                            </span>
                            <span>{item.label}</span>
                        </dt>
                        <dd>{item.value}</dd>
                        {item.hint && <p>{item.hint}</p>}
                    </div>
                );
            })}
        </dl>
    );
}
export function ProgressMeter({
    value,
    target,
    label,
    deadline,
    goalLabel = 'Meta',
    showGoal = true,
    compact = false,
}: {
    value: number;
    target: number;
    label: string;
    deadline?: Deadline | null;
    goalLabel?: string;
    showGoal?: boolean;
    compact?: boolean;
}) {
    const progress = progressValue(value, target);
    const [now, setNow] = useState<number | null>(null);
    useEffect(() => {
        const update = () => setNow(Date.now());
        update();
        const timer = window.setInterval(update, 60000);
        return () => window.clearInterval(timer);
    }, []);
    const color = organizerProgressColor(value, target, deadline, now);
    const Icon =
        color === 'success'
            ? CheckCircle2
            : color === 'warning'
              ? Clock3
              : Target;
    if (compact) {
        return (
            <div
                className="organizer-progress organizer-compact-progress"
                data-color={color}
                title={label}
            >
                <p>
                    {value.toLocaleString('pt-MZ')} /{' '}
                    {target.toLocaleString('pt-MZ')}
                </p>
                <div className="organizer-progress-track">
                    <progress
                        value={progress.value}
                        max={progress.max}
                        aria-label={label}
                    />
                </div>
            </div>
        );
    }
    return (
        <div className="organizer-progress" data-color={color}>
            <p>{label}</p>
            <div className="organizer-progress-track">
                <progress
                    value={progress.value}
                    max={progress.max}
                    aria-label={label}
                />
                <span
                    className="organizer-progress-marker"
                    aria-hidden="true"
                />
            </div>
            {showGoal && (
                <span className="organizer-progress-goal">
                    <Icon className="size-3.5" aria-hidden="true" />
                    {goalLabel} · {target.toLocaleString('pt-MZ')}
                </span>
            )}
        </div>
    );
}
export function DeadlineLabel({
    deadline,
    compact = false,
    blocked,
}: {
    deadline: Deadline | null;
    compact?: boolean;
    blocked?: boolean;
}) {
    return deadline ? (
        <span
            className="organizer-deadline"
            data-overdue={(blocked ?? deadline.isPast) || undefined}
        >
            {!compact && (
                <Clock3 className="size-4 shrink-0" aria-hidden="true" />
            )}
            <span>
                <time dateTime={deadline.value} title={deadline.detail}>
                    {deadline.label}
                </time>
                {!compact && <small>{deadline.detail}</small>}
            </span>
        </span>
    ) : null;
}
export function SummaryCard({
    title,
    items,
}: {
    title: string;
    items: { label: string; value: ReactNode }[];
}) {
    return (
        <OperationalPanel title={title}>
            <dl className="organizer-summary">
                {items.map((item) => (
                    <div key={item.label}>
                        <dt>{item.label}</dt>
                        <dd>{item.value ?? 'Não indicado'}</dd>
                    </div>
                ))}
            </dl>
        </OperationalPanel>
    );
}
export function TechnicalDetails({ children }: { children: ReactNode }) {
    return (
        <details className="organizer-technical">
            <summary>Detalhes técnicos</summary>
            <div className="mt-4 space-y-2 text-sm break-all">{children}</div>
        </details>
    );
}
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    onConfirm,
    confirmLabel,
    processing = false,
    danger = false,
    className = '',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    children?: ReactNode;
    onConfirm: () => void;
    confirmLabel: string;
    processing?: boolean;
    danger?: boolean;
    className?: string;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={`organizer-dialog max-h-[85svh] overflow-y-auto ${className}`}
                data-color={danger ? 'danger' : 'brand'}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
                <DialogFooter>
                    <button
                        type="button"
                        className="junta-btn junta-btn-md junta-btn-outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Voltar
                    </button>
                    <button
                        type="button"
                        className={
                            danger
                                ? 'organizer-btn-danger-solid junta-btn junta-btn-md'
                                : 'junta-btn junta-btn-md junta-btn-primary'
                        }
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        {processing ? 'A guardar…' : confirmLabel}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
export type DataColumn<T> = {
    key: string;
    label: string;
    render: (record: T) => ReactNode;
    desktop?: boolean;
    mobile?: boolean;
};
export function DataTable<T>({
    records,
    columns,
    label,
    rowKey,
    rowHref,
    empty,
    rowState,
    onRowClick,
}: {
    records: T[];
    columns: DataColumn<T>[];
    label: string;
    rowKey: (row: T) => string | number;
    rowHref?: (row: T) => string;
    onRowClick?: (row: T) => void;
    rowState?: (row: T) => VisibleState;
    empty?: ReactNode;
}) {
    const rowAppearance = (row: T) => {
        const state = rowState?.(row);
        const color = state?.blocker
            ? 'warning'
            : state
              ? organizerStateColor(state)
              : undefined;
        return {
            'data-color': color,
            'data-attention':
                state?.attention || color === 'warning' || color === 'danger'
                    ? true
                    : undefined,
        };
    };
    if (!records.length)
        return (
            empty ?? (
                <EmptyState
                    icon={Inbox}
                    title="Ainda não há registos"
                    description="Os registos desta etapa aparecerão aqui."
                />
            )
        );
    return (
        <>
            <div
                className="organizer-table-wrap hidden md:block"
                tabIndex={0}
                role="region"
                aria-label={label}
            >
                <table className="organizer-table">
                    <caption className="sr-only">{label}</caption>
                    <thead>
                        <tr>
                            {columns
                                .filter((column) => column.desktop !== false)
                                .map((column) => (
                                    <th scope="col" key={column.key}>
                                        {column.label}
                                    </th>
                                ))}
                        </tr>
                    </thead>
                    <tbody>
                        {records.map((row) => (
                            <tr
                                key={rowKey(row)}
                                {...rowAppearance(row)}
                                tabIndex={onRowClick ? 0 : undefined}
                                onKeyDown={(event) => {
                                    if (
                                        onRowClick &&
                                        event.target === event.currentTarget &&
                                        (event.key === 'Enter' ||
                                            event.key === ' ')
                                    ) {
                                        event.preventDefault();
                                        onRowClick(row);
                                    }
                                }}
                                className={
                                    rowHref || onRowClick
                                        ? 'cursor-pointer'
                                        : undefined
                                }
                                onClick={(event) => {
                                    if (
                                        (rowHref || onRowClick) &&
                                        !(event.target as Element).closest(
                                            'a,button,input,select,summary',
                                        )
                                    ) {
                                        if (onRowClick) {
                                            onRowClick(row);
                                        } else if (rowHref) {
                                            router.visit(rowHref(row));
                                        }
                                    }
                                }}
                            >
                                {columns
                                    .filter(
                                        (column) => column.desktop !== false,
                                    )
                                    .map((column) => (
                                        <td key={column.key}>
                                            {column.render(row)}
                                        </td>
                                    ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <ul className="grid gap-4 md:hidden" aria-label={label}>
                {records.map((row) => (
                    <li
                        key={rowKey(row)}
                        className="organizer-record-card"
                        {...rowAppearance(row)}
                        onClick={onRowClick ? () => onRowClick(row) : undefined}
                        tabIndex={onRowClick ? 0 : undefined}
                        onKeyDown={(event) => {
                            if (
                                onRowClick &&
                                event.target === event.currentTarget &&
                                event.key === 'Enter'
                            )
                                onRowClick(row);
                        }}
                    >
                        <dl>
                            {columns
                                .filter((column) => column.mobile !== false)
                                .map((column, index) => (
                                    <div
                                        key={column.key}
                                        data-primary={index === 0 || undefined}
                                    >
                                        <dt>{column.label}</dt>
                                        <dd>{column.render(row)}</dd>
                                    </div>
                                ))}
                        </dl>
                    </li>
                ))}
            </ul>
        </>
    );
}
