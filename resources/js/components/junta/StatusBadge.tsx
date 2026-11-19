import { organizerStateColor } from '@/lib/organizer-colors';
import {
    CheckCircle2,
    Clock3,
    CircleDashed,
    FileText,
    Package,
    Pencil,
    Users,
    Wallet,
    XCircle,
} from 'lucide-react';
import type { VisibleState } from '@/types/organizer';
import { Badge } from '@/components/ui/badge';
import type {
    CampaignStatus,
    ParticipationStatus,
    ProposalStatus,
} from '@/types/junta';

// ─── Campaign status labels & colours ────────────────────────────────────────

const campaignStatusConfig: Record<
    CampaignStatus,
    { label: string; className: string }
> = {
    rascunho: {
        label: 'Rascunho',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    ativa: {
        label: 'Ativa',
        className:
            'border-transparent bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]',
    },
    em_avaliacao: {
        label: 'Em Avaliação',
        className:
            'border-transparent bg-[var(--color-junta-warning-soft)] text-[var(--color-junta-warning)]',
    },
    proposta_selecionada: {
        label: 'Proposta Selecionada',
        className:
            'border-transparent bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]',
    },
    confirmada: {
        label: 'Confirmada',
        className:
            'border-transparent bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]',
    },
    em_fornecimento: {
        label: 'Em Fornecimento',
        className:
            'border-transparent bg-[var(--color-junta-warning-soft)] text-[var(--color-junta-warning)]',
    },
    concluida: {
        label: 'Concluída',
        className:
            'border-transparent bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]',
    },
    cancelada: {
        label: 'Cancelada',
        className:
            'border-transparent bg-[var(--color-junta-error-soft)] text-[var(--color-junta-error)]',
    },
};

const proposalStatusConfig: Record<
    ProposalStatus,
    { label: string; className: string }
> = {
    retirada: {
        label: 'Retirada pelo fornecedor',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    rascunho: {
        label: 'Rascunho',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    submetida: {
        label: 'Submetida',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
    em_avaliacao: {
        label: 'Em Avaliação',
        className:
            'border-transparent bg-[var(--color-junta-warning-soft)] text-[var(--color-junta-warning)]',
    },
    aceite: {
        label: 'Aceite',
        className:
            'border-transparent bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]',
    },
    recusada: {
        label: 'Recusada',
        className:
            'border-transparent bg-[var(--color-junta-error-soft)] text-[var(--color-junta-error)]',
    },
    expirada: {
        label: 'Expirada',
        className: 'border-transparent bg-muted text-muted-foreground',
    },
};

const participationStatusConfig: Record<
    ParticipationStatus,
    { label: string; className: string }
> = {
    pendente: {
        label: 'Pendente',
        className:
            'border-transparent bg-[var(--color-junta-warning-soft)] text-[var(--color-junta-warning)]',
    },
    confirmada: {
        label: 'Confirmada',
        className:
            'border-transparent bg-[var(--color-junta-success-soft)] text-[var(--color-junta-success)]',
    },
    cancelada: {
        label: 'Cancelada',
        className:
            'border-transparent bg-[var(--color-junta-error-soft)] text-[var(--color-junta-error)]',
    },
};

type CampaignStatusBadgeProps = {
    status: CampaignStatus;
    stage?: string;
    phase?: string;
    type?: never;
};

type ProposalStatusBadgeProps = {
    status: ProposalStatus;
    type: 'proposal';
};

type ParticipationStatusBadgeProps = {
    status: ParticipationStatus;
    type: 'participation';
};

type StatusBadgeProps =
    | CampaignStatusBadgeProps
    | ProposalStatusBadgeProps
    | ParticipationStatusBadgeProps;

export function StatusBadge(props: StatusBadgeProps) {
    if ('type' in props && props.type === 'proposal') {
        const config = proposalStatusConfig[props.status];
        return <Badge className={config.className}>{config.label}</Badge>;
    }

    if ('type' in props && props.type === 'participation') {
        const config = participationStatusConfig[props.status];
        return <Badge className={config.className}>{config.label}</Badge>;
    }

    const config = campaignStatusConfig[props.status];
    const phaseLabel = {
        payment: 'Pagamentos abertos',
        review: 'Pagamentos por confirmar',
        supply: 'Mercadoria a caminho',
        completed: 'Concluída',
        cancelled: 'Cancelada',
    }[props.stage ?? props.phase ?? ''];
    const phase = props.stage ?? props.phase ?? '';
    const phaseClass = ['payment', 'review'].includes(phase)
        ? 'border-transparent bg-junta-warning-soft text-junta-warning'
        : ['supply', 'completed'].includes(phase)
          ? 'border-transparent bg-junta-success-soft text-junta-success'
          : phase === 'cancelled'
            ? 'border-transparent bg-junta-error-soft text-junta-error'
            : config.className;
    return <Badge className={phaseClass}>{phaseLabel ?? config.label}</Badge>;
}

export function StatusPill({
    state,
    compact = false,
}: {
    compact?: boolean;
    state: Pick<VisibleState, 'label' | 'tone' | 'icon'> &
        Partial<Pick<VisibleState, 'code' | 'step'>>;
}) {
    const icons = {
        check: CheckCircle2,
        clock: Clock3,
        document: FileText,
        package: Package,
        pencil: Pencil,
        users: Users,
        wallet: Wallet,
        closed: XCircle,
    };
    const Icon = icons[state.icon as keyof typeof icons] ?? CircleDashed;
    return (
        <span
            className="organizer-status-pill"
            data-tone={state.tone}
            data-color={organizerStateColor(state)}
            data-compact={compact || undefined}
            title={compact ? state.label : undefined}
            data-solid={state.code === 'ready' || undefined}
        >
            <span className="organizer-status-dot" aria-hidden="true" />
            {!compact && (
                <Icon className="size-4 shrink-0" aria-hidden="true" />
            )}
            <span className="min-w-0 truncate">{state.label}</span>
        </span>
    );
}
