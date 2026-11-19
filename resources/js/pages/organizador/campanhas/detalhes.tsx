import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CampaignManagementLayout } from '@/components/junta/CampaignManagementLayout';
import { CampaignForecastPanel } from '@/components/junta/CampaignForecastPanel';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import {
    ConfirmDialog,
    ProgressMeter,
    SummaryCard,
} from '@/components/junta/OrganizerUi';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { campaignCategories } from '@/lib/campaign-presentation';
import { maputoDate } from '@/lib/organizer-presentation';
import { formatMZN } from '@/lib/formatters';
import { mutate, errorMessage } from '@/lib/server-mutation';
import { cancel, edit } from '@/routes/organizador/campanhas';
import type { OrganizerCampaign, OrganizerHistory } from '@/types/organizer';
export default function Manage() {
    const { organizerCampaign: view, organizerHistory: history } = usePage<{
        organizerCampaign: OrganizerCampaign;
        organizerHistory: OrganizerHistory[];
    }>().props;
    const [allHistory, setAllHistory] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState('');
    const campaign = view.campaign;
    const timeline = (entries: OrganizerHistory[]) => (
        <ol className="organizer-timeline">
            {entries.map((entry) => (
                <li key={entry.id}>
                    <p className="font-medium">{entry.label}</p>
                    <p className="organizer-caption">
                        {entry.actor} ·{' '}
                        <time dateTime={entry.createdAt}>
                            {maputoDate(entry.createdAt)} · Maputo
                        </time>
                    </p>
                </li>
            ))}
        </ol>
    );
    const doCancel = async () => {
        if (!reason.trim()) {
            setError('Indique o motivo do cancelamento.');
            return;
        }
        setProcessing(true);
        try {
            await mutate(cancel(campaign.id), { reason });
            setCancelling(false);
            setError('');
        } catch (failure) {
            setError(errorMessage(failure));
        } finally {
            setProcessing(false);
        }
    };
    return (
        <CampaignManagementLayout
            view={view}
            section="resumo"
            actions={
                view.canEdit && !view.state.isFinal ? (
                    <Link
                        href={edit(campaign.id)}
                        className="junta-btn junta-btn-md junta-btn-outline"
                    >
                        Editar campanha
                    </Link>
                ) : undefined
            }
        >
            <div className="organizer-columns">
                <div className="grid gap-6">
                    <OperationalPanel
                        emphasis="brand"
                        title={
                            view.state.isFinal
                                ? 'Resultado da campanha'
                                : (view.list.progress?.heading ??
                                  'Estado da campanha')
                        }
                    >
                        <p className="mb-4">
                            {view.state.description ||
                                (view.state.code === 'completed'
                                    ? 'A campanha terminou. Os pedidos e o histórico continuam disponíveis para consulta.'
                                    : 'A campanha foi cancelada. Consulte os pagamentos e devoluções no separador correspondente.')}
                        </p>
                        {['interest', 'payment', 'review', 'supply'].includes(
                            view.state.code,
                        ) ? (
                            <ProgressMeter
                                {...view.progress}
                                deadline={view.deadline}
                                goalLabel={
                                    ['payment', 'review'].includes(
                                        view.state.code,
                                    )
                                        ? 'Mínimo'
                                        : view.state.code === 'supply'
                                          ? 'Encomenda'
                                          : 'Meta'
                                }
                            />
                        ) : (
                            <p className="text-muted-foreground">
                                {view.metrics.participants} participantes ·{' '}
                                {view.metrics.proposals} propostas
                            </p>
                        )}
                    </OperationalPanel>
                    {view.state.code === 'interest' && (
                        <CampaignForecastPanel
                            key={campaign.id}
                            campaign={campaign}
                            sourceVersion={view.forecastVersion ?? ''}
                        />
                    )}
                    <OperationalPanel title="O produto">
                        <div className="grid gap-6">
                            <CampaignVisual campaign={campaign} />
                            <p className="whitespace-pre-wrap">
                                {campaign.description}
                            </p>
                        </div>
                    </OperationalPanel>
                    <OperationalPanel title="Histórico da campanha">
                        <div className="grid gap-4">
                            {history.length ? (
                                timeline(history.slice(0, 8))
                            ) : (
                                <p className="text-muted-foreground">
                                    Ainda não há alterações registadas. As
                                    decisões desta campanha aparecerão aqui.
                                </p>
                            )}
                            {history.length > 8 && (
                                <button
                                    className="junta-btn junta-btn-md justify-self-start junta-btn-outline"
                                    onClick={() => setAllHistory(true)}
                                >
                                    Ver tudo ({history.length})
                                </button>
                            )}
                        </div>
                    </OperationalPanel>
                </div>
                <aside className="grid gap-6">
                    <SummaryCard
                        title="Condições da campanha"
                        items={[
                            {
                                label: 'Categoria',
                                value: campaignCategories[campaign.category],
                            },
                            { label: 'Unidade', value: campaign.unit },
                            {
                                label: 'Meta de interesse',
                                value:
                                    campaign.targetQuantity.toLocaleString(
                                        'pt-MZ',
                                    ) +
                                    ' ' +
                                    campaign.unit,
                            },
                            {
                                label: 'Preço de referência · estimativa',
                                value:
                                    campaign.estimatedPrice == null
                                        ? 'Não indicado'
                                        : formatMZN(campaign.estimatedPrice),
                            },
                            {
                                label: 'Como receber',
                                value: view.deliveryLabel,
                            },
                            { label: 'Localização', value: campaign.location },
                            {
                                label: 'Prazo de interesse',
                                value:
                                    maputoDate(campaign.deadline) + ' · Maputo',
                            },
                            {
                                label: 'Organizador',
                                value: campaign.organizerName,
                            },
                        ]}
                    />
                    {!view.canEdit && !view.state.isFinal && (
                        <p className="organizer-caption">
                            {view.editBlockedReason}
                            {view.canCancel &&
                                ' Ainda pode cancelar com um motivo.'}
                        </p>
                    )}
                    {view.canCancel && !view.state.isFinal && (
                        <details className="organizer-danger">
                            <summary>Cancelar campanha</summary>
                            <p className="mt-4">
                                Antes de abrir pagamentos pode encerrar a
                                campanha, indicando o motivo.
                            </p>
                            <button
                                className="junta-btn mt-4 junta-btn-md junta-btn-outline"
                                onClick={() => setCancelling(true)}
                            >
                                Indicar motivo e cancelar
                            </button>
                        </details>
                    )}
                </aside>
            </div>
            <Dialog open={allHistory} onOpenChange={setAllHistory}>
                <DialogContent className="organizer-dialog max-h-[85svh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Histórico da campanha</DialogTitle>
                        <DialogDescription>
                            Até 30 alterações mais recentes, em hora de Maputo.
                        </DialogDescription>
                    </DialogHeader>
                    {timeline(history)}
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                open={cancelling}
                onOpenChange={setCancelling}
                title="Cancelar esta campanha?"
                description="A campanha será encerrada e o histórico será preservado. Confirme apenas quando tiver decidido não avançar."
                confirmLabel="Confirmar cancelamento"
                danger
                processing={processing}
                onConfirm={() => void doCancel()}
            >
                <label className="organizer-field">
                    Motivo
                    <textarea
                        className="junta-input"
                        maxLength={1000}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        disabled={processing}
                    />
                    <small>{reason.length}/1000 caracteres</small>
                </label>
                {error && (
                    <p role="alert" className="text-destructive">
                        {error}
                    </p>
                )}
            </ConfirmDialog>
        </CampaignManagementLayout>
    );
}
