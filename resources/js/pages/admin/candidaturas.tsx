import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Page, DetailList } from '@/components/junta/Page';
import { DataTable, ConfirmDialog } from '@/components/junta/OrganizerUi';
import { SegmentedControl } from '@/components/junta/ConsumerUi';
import { ResourceDrawer } from '@/components/junta/ResourceDrawer';
import {
    ResourceFilters,
    LabelPill,
    matches,
} from '@/components/junta/AdminResources';
import {
    usePrototypeStore,
    type OrganizerApplication,
} from '@/hooks/use-prototype-store';
import { maputoDate } from '@/lib/organizer-presentation';
import { update } from '@/routes/admin/candidaturas';
function Decision({
    application,
    decision,
    onClose,
}: {
    application: OrganizerApplication;
    decision: 'approved' | 'rejected';
    onClose: () => void;
}) {
    const form = useForm({ decision, reason: '' });
    const [invalid, setInvalid] = useState(false);
    return (
        <ConfirmDialog
            className="admin-dialog"
            open
            title={
                decision === 'approved'
                    ? 'Aprovar candidatura?'
                    : 'Rejeitar candidatura?'
            }
            description={
                decision === 'approved'
                    ? 'Concede acesso de organizador e notifica a pessoa da decisão.'
                    : 'Mantém a conta sem acesso de organizador e notifica a pessoa com o motivo indicado.'
            }
            confirmLabel={
                decision === 'approved'
                    ? 'Aprovar candidatura'
                    : 'Rejeitar candidatura'
            }
            processing={form.processing}
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
            onConfirm={() => {
                if (decision === 'rejected' && !form.data.reason.trim()) {
                    setInvalid(true);
                    return;
                }
                form.patch(update.url(application.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success(
                            'Decisão registada e comunicada na conta do candidato.',
                        );
                        onClose();
                    },
                });
            }}
        >
            <p>{application.fullName}</p>
            <label>
                {decision === 'rejected'
                    ? 'Motivo obrigatório'
                    : 'Observações (opcional)'}
                <textarea
                    className="junta-input"
                    name="reason"
                    required={decision === 'rejected'}
                    maxLength={2000}
                    disabled={form.processing}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </label>
            {invalid && (
                <p role="alert" className="text-destructive">
                    Indique o motivo da rejeição.
                </p>
            )}
            {Object.entries(form.errors).map(([key, error]) => (
                <p key={key} role="alert" className="text-destructive">
                    {error}
                </p>
            ))}
        </ConfirmDialog>
    );
}
export default function Applications() {
    const { data } = usePrototypeStore();
    const page = usePage();
    const id = Number(
        new URLSearchParams(page.url.split('?')[1]).get('applicationId'),
    );
    const initial = data.applications.find((a) => a.id === id) ?? null;
    const [tab, setTab] = useState(
        initial && initial.status !== 'pending' ? 'decided' : 'pending',
    );
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<OrganizerApplication | null>(
        initial,
    );
    const [decision, setDecision] = useState<{
        application: OrganizerApplication;
        decision: 'approved' | 'rejected';
    } | null>(null);
    return (
        <Page
            title="Candidaturas"
            description="Avalie os pedidos de acesso de organizador."
        >
            <SegmentedControl
                label="Estado das candidaturas"
                value={tab}
                onChange={setTab}
                options={[
                    {
                        value: 'pending',
                        label:
                            'Pendentes (' +
                            data.applications.filter(
                                (a) => a.status === 'pending',
                            ).length +
                            ')',
                    },
                    { value: 'decided', label: 'Decididas' },
                ]}
            />
            <section className="admin-panel">
                <ResourceFilters
                    search={search}
                    onSearch={setSearch}
                    placeholder="Nome, email ou localidade"
                />
                <DataTable
                    records={data.applications.filter(
                        (a) =>
                            (tab === 'pending'
                                ? a.status === 'pending'
                                : a.status !== 'pending') &&
                            matches(search, a.fullName, a.email, a.location),
                    )}
                    rowKey={(a) => a.id}
                    onRowClick={setSelected}
                    label="Candidaturas"
                    columns={[
                        {
                            key: 'candidate',
                            label: 'Candidato',
                            render: (a) => a.fullName,
                        },
                        {
                            key: 'location',
                            label: 'Localidade',
                            render: (a) => a.location,
                        },
                        {
                            key: 'date',
                            label: 'Data',
                            render: (a) => maputoDate(a.createdAt),
                        },
                        {
                            key: 'status',
                            label: tab === 'pending' ? 'Acção' : 'Estado',
                            render: (a) =>
                                a.status === 'pending' ? (
                                    <button
                                        className="junta-btn junta-btn-sm junta-btn-outline"
                                        onClick={() => setSelected(a)}
                                    >
                                        Rever
                                    </button>
                                ) : (
                                    <LabelPill
                                        label={
                                            a.status === 'approved'
                                                ? 'Aprovada'
                                                : 'Rejeitada'
                                        }
                                        tone={
                                            a.status === 'approved'
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    />
                                ),
                        },
                    ]}
                />
            </section>
            {selected && (
                <ResourceDrawer
                    title={selected.fullName}
                    description="Candidatura a organizador"
                    onClose={() => setSelected(null)}
                >
                    <DetailList
                        items={[
                            { label: 'Email', value: selected.email },
                            { label: 'Telefone', value: selected.phone },
                            { label: 'Localidade', value: selected.location },
                            {
                                label: 'Enviada',
                                value: maputoDate(selected.createdAt),
                            },
                        ]}
                    />
                    <section>
                        <h3>Motivação</h3>
                        <p className="whitespace-pre-wrap">
                            {selected.motivation}
                        </p>
                    </section>
                    {selected.status === 'pending' ? (
                        <div className="admin-resource-actions">
                            <button
                                className="junta-btn junta-btn-md junta-btn-primary"
                                onClick={() => {
                                    setDecision({
                                        application: selected,
                                        decision: 'approved',
                                    });
                                    setSelected(null);
                                }}
                            >
                                Aprovar
                            </button>
                            <button
                                className="junta-btn junta-btn-md junta-btn-outline"
                                onClick={() => {
                                    setDecision({
                                        application: selected,
                                        decision: 'rejected',
                                    });
                                    setSelected(null);
                                }}
                            >
                                Rejeitar
                            </button>
                        </div>
                    ) : (
                        <section>
                            <h3>Decisão registada</h3>
                            <p>
                                {selected.status === 'approved'
                                    ? 'Aprovada'
                                    : 'Rejeitada'}{' '}
                                · {selected.reviewerName}
                            </p>
                            <p>
                                {selected.reviewedAt
                                    ? maputoDate(selected.reviewedAt)
                                    : ''}
                            </p>
                            <p>
                                {selected.decisionReason ||
                                    'Sem observações adicionais.'}
                            </p>
                        </section>
                    )}
                </ResourceDrawer>
            )}
            {decision && (
                <Decision {...decision} onClose={() => setDecision(null)} />
            )}
        </Page>
    );
}
