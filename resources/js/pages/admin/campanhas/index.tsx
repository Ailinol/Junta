import { maputoInput, maputoDate } from '@/lib/organizer-presentation';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Page, DetailList } from '@/components/junta/Page';
import {
    DataTable,
    DeadlineLabel,
    ProgressMeter,
    TechnicalDetails,
} from '@/components/junta/OrganizerUi';
import { StatusPill } from '@/components/junta/StatusBadge';
import {
    ResourceDrawer,
    ResourceEditor,
    ResourceRemoval,
    type EditorField,
} from '@/components/junta/ResourceDrawer';
import { ResourceFilters, matches } from '@/components/junta/AdminResources';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { campaignCategories, deliveryLabel } from '@/lib/campaign-presentation';
import { formatMZN } from '@/lib/formatters';
import { index as finance } from '@/routes/admin/compras';
import { show } from '@/routes/organizador/campanhas';
import { store, update, destroy } from '@/routes/admin/campanhas';
import type { AdminCampaign } from '@/types/admin';
export default function Campaigns({
    adminCampaigns,
}: {
    adminCampaigns: AdminCampaign[];
}) {
    const { data } = usePrototypeStore();
    const [search, setSearch] = useState('');
    const [phase, setPhase] = useState('');
    const [selected, setSelected] = useState<AdminCampaign | null>(null);
    const [editing, setEditing] = useState<AdminCampaign | 'new' | null>(null);
    const [removing, setRemoving] = useState<AdminCampaign | null>(null);
    const campaign = editing && editing !== 'new' ? editing.campaign : null;
    const fields: EditorField[] = [
        { key: 'title', label: 'Título', required: true, section: 'Produto' },
        {
            key: 'organizerId',
            label: 'Organizador',
            type: 'select',
            required: true,
            section: 'Produto',
            options: data.users
                .filter(
                    (user) =>
                        user.roles.includes('organizador') ||
                        user.roles.includes('admin'),
                )
                .map((user) => ({ value: String(user.id), label: user.name })),
        },
        {
            key: 'category',
            label: 'Categoria',
            type: 'select',
            required: true,
            section: 'Produto',
            options: Object.entries(campaignCategories).map(
                ([value, label]) => ({ value, label }),
            ),
        },
        {
            key: 'description',
            label: 'Descrição',
            type: 'textarea',
            required: true,
            section: 'Produto',
        },
        {
            key: 'imageUrl',
            label: 'URL da imagem',
            type: 'url',
            section: 'Produto',
        },
        { key: 'unit', label: 'Unidade', required: true, section: 'Condições' },
        {
            key: 'targetQuantity',
            label: 'Quantidade pretendida',
            type: 'number',
            required: true,
            section: 'Condições',
        },
        {
            key: 'estimatedPrice',
            label: 'Preço estimado por unidade (MT)',
            type: 'number',
            section: 'Condições',
        },
        {
            key: 'deadline',
            label: 'Fim do interesse (Maputo)',
            type: 'datetime-local',
            required: true,
            section: 'Condições',
        },
        {
            key: 'location',
            label: 'Localização',
            required: true,
            section: 'Condições',
        },
        {
            key: 'deliveryMode',
            label: 'Modalidade',
            type: 'select',
            required: true,
            section: 'Condições',
            options: [
                { value: 'recolha', label: 'Recolha' },
                { value: 'entrega', label: 'Entrega' },
                { value: 'ambos', label: 'Recolha e entrega' },
            ],
        },
    ];
    return (
        <Page
            title="Campanhas"
            description="Consulte as condições e a evolução das campanhas."
            actions={
                <button
                    className="junta-btn junta-btn-md junta-btn-primary"
                    onClick={() => setEditing('new')}
                >
                    Adicionar campanha
                </button>
            }
        >
            <section className="admin-panel">
                <ResourceFilters
                    search={search}
                    onSearch={setSearch}
                    placeholder="Título, referência, localização ou organizador"
                    value={phase}
                    onFilter={setPhase}
                    options={[
                        ...new Map(
                            adminCampaigns.map((view) => [
                                view.workspace.code,
                                view.workspace.label,
                            ]),
                        ).entries(),
                    ].map(([value, label]) => ({ value, label }))}
                />
                <DataTable
                    records={adminCampaigns.filter(
                        (view) =>
                            (!phase || view.workspace.code === phase) &&
                            matches(
                                search,
                                view.campaign.title,
                                view.campaign.reference,
                                view.campaign.organizerName,
                                view.campaign.location,
                            ),
                    )}
                    label="Campanhas"
                    rowKey={(view) => view.campaign.id}
                    onRowClick={setSelected}
                    columns={[
                        {
                            key: 'title',
                            label: 'Título',
                            render: (view) => view.campaign.title,
                        },
                        {
                            key: 'owner',
                            label: 'Organizador',
                            render: (view) => view.campaign.organizerName,
                        },
                        {
                            key: 'phase',
                            label: 'Fase',
                            render: (view) => (
                                <StatusPill compact state={view.workspace} />
                            ),
                        },
                        {
                            key: 'interest',
                            label: 'Procura',
                            render: (view) => (
                                <ProgressMeter
                                    compact
                                    value={view.metrics.interestUnits}
                                    target={view.campaign.targetQuantity}
                                    label={'Interesse · ' + view.campaign.unit}
                                />
                            ),
                        },
                        {
                            key: 'deadline',
                            label: 'Prazo',
                            render: (view) => (
                                <DeadlineLabel
                                    deadline={view.workspace.deadline}
                                    compact
                                    blocked={view.workspace.deadline?.blocked}
                                />
                            ),
                        },
                    ]}
                />
            </section>
            {selected && (
                <ResourceDrawer
                    title={selected.campaign.title}
                    onClose={() => setSelected(null)}
                >
                    <StatusPill state={selected.workspace} compact />
                    <p>{selected.campaign.description}</p>
                    <DetailList
                        items={[
                            {
                                label: 'Organizador',
                                value: selected.campaign.organizerName,
                            },
                            {
                                label: 'Localização',
                                value: selected.campaign.location,
                            },
                            {
                                label: 'Quantidade pretendida',
                                value:
                                    selected.campaign.targetQuantity +
                                    ' ' +
                                    selected.campaign.unit,
                            },
                            {
                                label: 'Preço estimado',
                                value: selected.campaign.estimatedPrice
                                    ? formatMZN(
                                          selected.campaign.estimatedPrice,
                                      )
                                    : 'Não indicado',
                            },
                            {
                                label: 'Fim do interesse',
                                value: maputoDate(selected.campaign.deadline),
                            },
                            {
                                label: 'Modalidade',
                                value: deliveryLabel(
                                    selected.campaign.deliveryMode,
                                ),
                            },
                        ]}
                    />
                    <Link
                        className="junta-btn junta-btn-md junta-btn-primary"
                        href={
                            selected.hasPurchase
                                ? finance({
                                      query: {
                                          campaignId: selected.campaign.id,
                                      },
                                  })
                                : show(selected.campaign.id)
                        }
                    >
                        Gerir campanha
                    </Link>
                    {['payment', 'review'].includes(
                        selected.campaign.purchasePhase ?? '',
                    ) && (
                        <Link
                            className="text-sm text-primary underline"
                            href={finance({
                                query: {
                                    campaignId: selected.campaign.id,
                                    tab: 'encerramento',
                                },
                            })}
                        >
                            Cancelar pelo fluxo oficial
                        </Link>
                    )}
                    {!selected.workspace.isFinal && (
                        <>
                            <button
                                className="junta-btn junta-btn-md junta-btn-outline"
                                disabled={!selected.canEdit}
                                onClick={() => {
                                    setEditing(selected);
                                    setSelected(null);
                                }}
                            >
                                Editar condições
                            </button>
                            {!selected.canEdit && (
                                <p className="admin-muted">
                                    {selected.editBlockedReason}
                                </p>
                            )}
                            <button
                                className="junta-btn junta-btn-md junta-btn-outline"
                                disabled={!selected.canRemove}
                                onClick={() => {
                                    setRemoving(selected);
                                    setSelected(null);
                                }}
                            >
                                Remover
                            </button>
                            {!selected.canRemove && (
                                <p className="admin-muted">
                                    A campanha tem participações ou propostas
                                    associadas e não pode ser removida.
                                </p>
                            )}
                        </>
                    )}
                    <TechnicalDetails>
                        <p>{selected.campaign.reference}</p>
                    </TechnicalDetails>
                </ResourceDrawer>
            )}
            {editing && (
                <ResourceEditor
                    title={campaign ? 'Editar condições' : 'Adicionar campanha'}
                    fields={fields}
                    initial={{
                        title: campaign?.title ?? '',
                        organizerId: campaign?.organizerId ?? '',
                        category: campaign?.category ?? 'outro',
                        description: campaign?.description ?? '',
                        imageUrl: campaign?.imageUrl ?? '',
                        status: campaign?.status ?? 'rascunho',
                        unit: campaign?.unit ?? '',
                        targetQuantity: campaign?.targetQuantity ?? '',
                        estimatedPrice: campaign?.estimatedPrice ?? '',
                        deadline: maputoInput(campaign?.deadline ?? ''),
                        location: campaign?.location ?? '',
                        deliveryMode: campaign?.deliveryMode ?? 'recolha',
                    }}
                    route={campaign ? update(campaign.id) : store()}
                    onClose={() => setEditing(null)}
                />
            )}
            {removing && (
                <ResourceRemoval
                    label={removing.campaign.title}
                    route={destroy(removing.campaign.id)}
                    onClose={() => setRemoving(null)}
                />
            )}
        </Page>
    );
}
