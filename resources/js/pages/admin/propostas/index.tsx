import { deliveryLabel } from '@/lib/campaign-presentation';
import { humanDeadline } from '@/lib/consumer-presentation';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Page, DetailList } from '@/components/junta/Page';
import {
    DataTable,
    TechnicalDetails,
    DeadlineLabel,
} from '@/components/junta/OrganizerUi';
import {
    ResourceDrawer,
    ResourceEditor,
    ResourceRemoval,
    type EditorField,
} from '@/components/junta/ResourceDrawer';
import {
    ResourceFilters,
    LabelPill,
    matches,
} from '@/components/junta/AdminResources';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { formatMZN } from '@/lib/formatters';
import { maputoDate, maputoInput } from '@/lib/organizer-presentation';
import { propostas as comparison } from '@/routes/organizador/campanhas';
import { store, update, destroy } from '@/routes/admin/propostas';
import type { Proposal } from '@/types/junta';
const labels: Record<string, string> = {
    submetida: 'Enviada',
    rascunho: 'Rascunho',
    em_avaliacao: 'Em análise',
    aceite: 'Seleccionada',
    recusada: 'Não seleccionada',
    retirada: 'Retirada',
    expirada: 'Expirada',
};
export default function Proposals() {
    const { data } = usePrototypeStore();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [selected, setSelected] = useState<Proposal | null>(null);
    const [editing, setEditing] = useState<Proposal | 'new' | null>(null);
    const [removing, setRemoving] = useState<Proposal | null>(null);
    const proposal = editing && editing !== 'new' ? editing : null;
    const campaigns = data.campaigns.filter((c) => c.acceptsProposals);
    const suppliers = data.suppliers.filter((s) => s.verified);
    const editable = (p: Proposal) =>
        !['aceite', 'retirada', 'expirada', 'recusada'].includes(p.status) &&
        campaigns.some((c) => c.id === p.campaignId);
    const fields: EditorField[] = [
        {
            key: 'campaignId',
            label: 'Campanha',
            type: 'select',
            required: true,
            options: (proposal ? [proposal.campaign] : campaigns).map((c) => ({
                value: String(c.id),
                label: c.title,
            })),
        },
        {
            key: 'supplierId',
            label: 'Fornecedor verificado',
            type: 'select',
            required: true,
            options: suppliers.map((s) => ({
                value: String(s.id),
                label: s.companyName,
            })),
        },
        {
            key: 'unitPrice',
            label: 'Preço unitário (MT)',
            type: 'number',
            required: true,
        },
        {
            key: 'availableQuantity',
            label: 'Capacidade disponível',
            type: 'number',
            required: true,
        },
        {
            key: 'minimumQuantity',
            label: 'Mínimo comercial',
            type: 'number',
            required: true,
        },
        {
            key: 'totalEstimated',
            label: 'Total declarado (MT)',
            type: 'number',
        },
        {
            key: 'deliveryDays',
            label: 'Prazo de fornecimento (dias)',
            type: 'number',
            required: true,
        },
        {
            key: 'deliveryMode',
            label: 'Modalidade',
            type: 'select',
            required: true,
            options: [
                { value: 'recolha', label: 'Recolha' },
                { value: 'entrega', label: 'Entrega' },
                { value: 'ambos', label: 'Recolha e entrega' },
            ],
        },
        {
            key: 'validUntil',
            label: 'Validade do preço (Maputo)',
            type: 'datetime-local',
            required: true,
        },
        {
            key: 'reservedUntil',
            label: 'Reserva de quantidade (Maputo)',
            type: 'datetime-local',
            required: true,
        },
        {
            key: 'transportIncluded',
            label: 'Transporte incluído',
            type: 'boolean',
        },
        {
            key: 'transportUnitPrice',
            label: 'Transporte adicional por unidade (MT)',
            type: 'number',
            required: true,
            hint: 'Quando incluído, indique zero.',
        },
        { key: 'notes', label: 'Condições adicionais', type: 'textarea' },
    ];
    return (
        <Page
            title="Propostas"
            description="Consulte as condições comerciais e a selecção de propostas."
            actions={
                <button
                    className="junta-btn junta-btn-md junta-btn-primary"
                    disabled={!campaigns.length || !suppliers.length}
                    onClick={() => setEditing('new')}
                >
                    Adicionar proposta
                </button>
            }
        >
            <section className="admin-panel">
                <ResourceFilters
                    search={search}
                    onSearch={setSearch}
                    placeholder="Campanha ou fornecedor"
                    value={status}
                    onFilter={setStatus}
                    options={Object.entries(labels).map(([value, label]) => ({
                        value,
                        label,
                    }))}
                />
                <DataTable
                    records={data.proposals.filter(
                        (p) =>
                            (!status || p.status === status) &&
                            matches(search, p.campaign.title, p.supplierName),
                    )}
                    label="Propostas"
                    rowKey={(p) => p.id}
                    onRowClick={setSelected}
                    columns={[
                        {
                            key: 'campaign',
                            label: 'Campanha',
                            render: (p) => p.campaign.title,
                        },
                        {
                            key: 'supplier',
                            label: 'Fornecedor',
                            render: (p) => p.supplierName,
                        },
                        {
                            key: 'price',
                            label: 'Preço unitário',
                            render: (p) => formatMZN(p.unitPrice),
                        },
                        {
                            key: 'capacity',
                            label: 'Capacidade / mínimo',
                            render: (p) =>
                                p.availableQuantity +
                                ' / ' +
                                (p.minimumQuantity ?? '—'),
                        },
                        {
                            key: 'state',
                            label: 'Estado',
                            render: (p) => (
                                <div>
                                    <LabelPill
                                        label={labels[p.status]}
                                        tone={
                                            p.status === 'aceite'
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    />
                                    <small>
                                        <DeadlineLabel
                                            deadline={humanDeadline(
                                                p.validUntil,
                                            )}
                                            compact
                                            blocked={false}
                                        />
                                    </small>
                                </div>
                            ),
                        },
                    ]}
                />
            </section>
            {selected && (
                <ResourceDrawer
                    title={selected.campaign.title}
                    description={selected.supplierName}
                    onClose={() => setSelected(null)}
                >
                    <LabelPill
                        label={labels[selected.status]}
                        tone={
                            selected.status === 'aceite' ? 'success' : 'neutral'
                        }
                    />
                    <DetailList
                        items={[
                            {
                                label: 'Preço unitário',
                                value: formatMZN(selected.unitPrice),
                            },
                            {
                                label: 'Capacidade / mínimo',
                                value:
                                    selected.availableQuantity +
                                    ' / ' +
                                    (selected.minimumQuantity ??
                                        'Não indicado'),
                            },
                            {
                                label: 'Total declarado',
                                value: formatMZN(selected.totalEstimated),
                            },
                            {
                                label: 'Modalidade',
                                value: deliveryLabel(selected.deliveryMode),
                            },
                            {
                                label: 'Prazo de fornecimento',
                                value: selected.deliveryDays + ' dias',
                            },
                            {
                                label: 'Validade do preço',
                                value: maputoDate(selected.validUntil),
                            },
                            {
                                label: 'Reserva de quantidade',
                                value: selected.reservedUntil
                                    ? maputoDate(selected.reservedUntil)
                                    : 'Não indicada',
                            },
                            {
                                label: 'Transporte',
                                value: selected.transportIncluded
                                    ? 'Incluído'
                                    : selected.transportUnitPrice != null
                                      ? formatMZN(selected.transportUnitPrice) +
                                        ' / unidade'
                                      : 'Não indicado',
                            },
                        ]}
                    />
                    <p>{selected.notes || 'Sem condições adicionais.'}</p>
                    <Link
                        className="junta-btn junta-btn-md junta-btn-outline"
                        href={comparison(selected.campaignId)}
                    >
                        Ver comparação de propostas
                    </Link>
                    {selected.status === 'aceite' ? (
                        <p className="admin-muted">
                            As condições da proposta seleccionada ficam
                            preservadas.
                        </p>
                    ) : editable(selected) ? (
                        <div className="admin-resource-actions">
                            <button
                                className="junta-btn junta-btn-md junta-btn-outline"
                                onClick={() => {
                                    setEditing(selected);
                                    setSelected(null);
                                }}
                            >
                                Editar
                            </button>
                            <button
                                className="junta-btn junta-btn-md junta-btn-outline"
                                onClick={() => {
                                    setRemoving(selected);
                                    setSelected(null);
                                }}
                            >
                                Remover
                            </button>
                        </div>
                    ) : (
                        <p className="admin-muted">
                            Este registo está disponível para consulta. A
                            campanha ou a proposta já não permite alterações.
                        </p>
                    )}
                    <TechnicalDetails>
                        <p>Proposta: {selected.id}</p>
                    </TechnicalDetails>
                </ResourceDrawer>
            )}
            {editing && (
                <ResourceEditor
                    title={proposal ? 'Editar proposta' : 'Adicionar proposta'}
                    fields={fields}
                    initial={{
                        campaignId: proposal?.campaignId ?? '',
                        supplierId: proposal?.supplierId ?? '',
                        unitPrice: proposal?.unitPrice ?? '',
                        availableQuantity: proposal?.availableQuantity ?? '',
                        minimumQuantity: proposal?.minimumQuantity ?? '',
                        totalEstimated: proposal?.totalEstimated ?? '',
                        deliveryDays: proposal?.deliveryDays ?? '',
                        deliveryMode: proposal?.deliveryMode ?? 'entrega',
                        validUntil: maputoInput(proposal?.validUntil ?? ''),
                        reservedUntil: maputoInput(
                            proposal?.reservedUntil ?? '',
                        ),
                        transportIncluded: proposal?.transportIncluded ?? false,
                        transportUnitPrice: proposal?.transportUnitPrice ?? '',
                        status: proposal?.status ?? 'submetida',
                        notes: proposal?.notes ?? '',
                    }}
                    route={proposal ? update(proposal.id) : store()}
                    onClose={() => setEditing(null)}
                />
            )}
            {removing && (
                <ResourceRemoval
                    label={
                        removing.supplierName + ' · ' + removing.campaign.title
                    }
                    route={destroy(removing.id)}
                    onClose={() => setRemoving(null)}
                />
            )}
        </Page>
    );
}
