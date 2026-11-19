import type { ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Page } from '@/components/junta/Page';
import { DataTable, TechnicalDetails } from '@/components/junta/OrganizerUi';
import { FilterBar } from '@/components/junta/OrganizerConsole';
import { ResourceDrawer } from '@/components/junta/ResourceDrawer';
import { PurchasePagination } from '@/components/junta/PurchaseForms';
import { maputoDate } from '@/lib/organizer-presentation';
import { auditLabels } from '@/lib/audit-labels';
import { historico } from '@/routes/admin';
import type { AuditRecord } from '@/types/admin';
import type { PurchasePage } from '@/types/purchase';
export { auditLabels } from '@/lib/audit-labels';
const entities: Record<string, string> = {
    Campaign: 'Campanha',
    Proposal: 'Proposta',
    Supplier: 'Fornecedor',
    User: 'Utilizador',
    OrganizerApplication: 'Candidatura',
    PurchaseOrder: 'Pedido',
    FinancialEntry: 'Movimento financeiro',
    PurchaseIssue: 'Ocorrência',
    SupplyOrder: 'Fornecimento',
};
const fields: Record<string, string> = {
    title: 'Título',
    company_name: 'Razão social',
    nuit: 'NUIT',
    contact_name: 'Contacto',
    email: 'Email',
    phone: 'Telefone',
    organizer_name: 'Organizador',
    unit: 'Unidade',
    target_quantity: 'Quantidade pretendida',
    estimated_price: 'Preço estimado (MT)',
    deadline: 'Fim do interesse',
    delivery_mode: 'Modalidade',
    available_quantity: 'Capacidade',
    minimum_quantity: 'Mínimo comercial',
    valid_until: 'Validade do preço',
    reserved_until: 'Reserva de quantidade',
    transport_included: 'Transporte incluído',
    status: 'Estado',
    quantity: 'Quantidade',
    reason: 'Motivo',
    verified: 'Verificado',
    unit_price: 'Preço unitário',
    amount_cents: 'Valor em cêntimos',
    before: 'Antes',
    after: 'Depois',
    old: 'Antes',
    new: 'Depois',
    decision: 'Decisão',
    name: 'Nome',
    description: 'Descrição',
    location: 'Localização',
};
function auditValue(value: unknown): ReactNode {
    if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
    if (value === null) return '—';
    if (typeof value === 'object')
        return (
            <dl>
                {Object.entries(value)
                    .filter(
                        ([key]) =>
                            key in fields &&
                            !['before', 'after', 'old', 'new'].includes(key),
                    )
                    .map(([key, content]) => (
                        <div key={key}>
                            <dt>{fields[key]}</dt>
                            <dd>{auditValue(content)}</dd>
                        </div>
                    ))}
            </dl>
        );
    const labels: Record<string, string> = {
        ativa: 'Interesse aberto',
        rascunho: 'Rascunho',
        em_avaliacao: 'Em avaliação',
        proposta_selecionada: 'Proposta seleccionada',
        confirmada: 'Condições fixadas',
        em_fornecimento: 'Fornecimento',
        concluida: 'Concluída',
        cancelada: 'Cancelada',
        submetida: 'Enviada',
        aceite: 'Seleccionada',
        recusada: 'Não seleccionada',
        retirada: 'Retirada',
        expirada: 'Expirada',
        approved: 'Aprovada',
        rejected: 'Rejeitada',
        pending: 'Pendente',
    };
    if (typeof value === 'string') return labels[value] ?? value;
    return typeof value === 'number' ? value.toLocaleString('pt-MZ') : '—';
}
export default function History({
    adminHistory,
}: {
    adminHistory: {
        records: PurchasePage<AuditRecord>;
        filters: { search: string; action: string; from: string; to: string };
        actions: string[];
    };
}) {
    const [filters, setFilters] = useState(adminHistory.filters);
    const [selected, setSelected] = useState<AuditRecord | null>(null);
    return (
        <Page
            title="Histórico"
            description="Arquivo das operações administrativas e de compra."
        >
            <section className="admin-panel">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(historico.url(), filters, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <FilterBar>
                        <input
                            className="junta-input"
                            aria-label="Pesquisar histórico"
                            placeholder="Pessoa, operação ou entidade"
                            value={filters.search}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    search: event.target.value,
                                })
                            }
                        />
                        <select
                            className="junta-input"
                            aria-label="Operação"
                            value={filters.action}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    action: event.target.value,
                                })
                            }
                        >
                            <option value="">Todas as operações</option>
                            {adminHistory.actions.map((action) => (
                                <option key={action} value={action}>
                                    {auditLabels[action] ?? 'Outro registo'}
                                </option>
                            ))}
                        </select>
                        <label>
                            Desde
                            <input
                                className="junta-input"
                                type="date"
                                value={filters.from}
                                onChange={(event) =>
                                    setFilters({
                                        ...filters,
                                        from: event.target.value,
                                    })
                                }
                            />
                        </label>
                        <label>
                            Até
                            <input
                                className="junta-input"
                                type="date"
                                value={filters.to}
                                onChange={(event) =>
                                    setFilters({
                                        ...filters,
                                        to: event.target.value,
                                    })
                                }
                            />
                        </label>
                        <button className="junta-btn junta-btn-md junta-btn-outline">
                            Aplicar
                        </button>
                    </FilterBar>
                </form>
                <DataTable
                    records={adminHistory.records.data}
                    rowKey={(entry) => entry.id}
                    onRowClick={setSelected}
                    label="Histórico"
                    columns={[
                        {
                            key: 'date',
                            label: 'Data',
                            render: (entry) => maputoDate(entry.createdAt),
                        },
                        {
                            key: 'actor',
                            label: 'Autor',
                            render: (entry) => entry.actor,
                        },
                        {
                            key: 'action',
                            label: 'Operação',
                            render: (entry) =>
                                auditLabels[entry.action] ??
                                'Registo actualizado',
                        },
                        {
                            key: 'entity',
                            label: 'Entidade',
                            render: (entry) =>
                                entities[
                                    entry.subject.split('\\').pop() ?? ''
                                ] ?? 'Registo da plataforma',
                        },
                    ]}
                />
                <PurchasePagination page={adminHistory.records} />
            </section>
            {selected && (
                <ResourceDrawer
                    title={
                        auditLabels[selected.action] ?? 'Registo actualizado'
                    }
                    description={
                        selected.actor + ' · ' + maputoDate(selected.createdAt)
                    }
                    onClose={() => setSelected(null)}
                >
                    <section>
                        <h3>Alterações registadas</h3>
                        {Object.entries(selected.changes)
                            .filter(([key]) => key in fields)
                            .map(([key, value]) => (
                                <div className="admin-audit-change" key={key}>
                                    <strong>{fields[key]}</strong>
                                    {auditValue(value)}
                                </div>
                            ))}
                        {!Object.keys(selected.changes).some(
                            (key) => key in fields,
                        ) && (
                            <p>
                                O registo não contém uma comparação de campos.
                                Consulte os detalhes técnicos para o conteúdo
                                integral.
                            </p>
                        )}
                    </section>
                    <TechnicalDetails>
                        <p>Operação: {selected.action}</p>
                        <p>
                            {selected.subject} · {selected.subjectId}
                        </p>
                        <pre>{JSON.stringify(selected.changes, null, 2)}</pre>
                    </TechnicalDetails>
                </ResourceDrawer>
            )}
        </Page>
    );
}
