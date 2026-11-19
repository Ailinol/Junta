import { FilterBar } from '@/components/junta/OrganizerConsole';
import { useRemember } from '@inertiajs/react';
import { useState } from 'react';

import { DataTable, type DataColumn } from '@/components/junta/OrganizerUi';
import { StatusPill } from '@/components/junta/StatusBadge';
import { EmptyState } from '@/components/junta/EmptyState';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { money } from '@/components/junta/PurchaseForms';
import { csvValue, maputoDate } from '@/lib/organizer-presentation';
import type {
    OrganizerCampaign,
    OrganizerParticipant,
} from '@/types/organizer';
export function OrganizerParticipantsTable({
    view,
    participants,
}: {
    view: OrganizerCampaign;
    participants: OrganizerParticipant[];
}) {
    const [filters, setFilters] = useRemember(
        { search: '', state: '' },
        `participants-${view.campaign.id}`,
    );
    const [notes, setNotes] = useState<OrganizerParticipant | null>(null);
    const rows = participants.filter(
        (row) =>
            row.name
                .toLocaleLowerCase()
                .includes(filters.search.trim().toLocaleLowerCase()) &&
            (!filters.state || row.state.code === filters.state),
    );
    const states = Array.from(
        new Map(participants.map((row) => [row.state.code, row.state.label])),
    );
    const payments = participants.some((row) => row.orderId !== null);
    const exportCsv = () => {
        const fields = [
            'Nome',
            'Telefone',
            'Quantidade',
            'Estado',
            'Data',
            'Observações',
            ...(payments ? ['Valor (MZN)'] : []),
        ];
        const lines = rows.map((row) => [
            row.name,
            row.phone,
            row.quantity,
            row.state.label,
            maputoDate(row.joinedAt),
            row.notes,
            ...(payments
                ? [
                      row.amountCents == null
                          ? ''
                          : (row.amountCents / 100).toFixed(2),
                  ]
                : []),
        ]);
        const blob = new Blob(
            [
                '\uFEFF' +
                    [fields, ...lines]
                        .map((line) => line.map(csvValue).join(';'))
                        .join('\r\n'),
            ],
            { type: 'text/csv;charset=utf-8;' },
        );
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `participantes-JNT-${view.campaign.id}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };
    const name: DataColumn<OrganizerParticipant> = {
        key: 'name',
        label: 'Nome',
        render: (row) => <strong>{row.name}</strong>,
    };
    const quantity: DataColumn<OrganizerParticipant> = {
        key: 'quantity',
        label: 'Quantidade',
        render: (row) => `${row.quantity} ${view.campaign.unit}`,
    };
    const phone: DataColumn<OrganizerParticipant> = {
        key: 'phone',
        label: 'Telefone',
        mobile: false,
        render: (row) =>
            row.phone ? (
                <a href={'tel:' + row.phone.replace(/[^+0-9]/g, '')}>
                    {row.phone}
                </a>
            ) : (
                'Não indicado'
            ),
    };
    const state: DataColumn<OrganizerParticipant> = {
        key: 'state',
        label: 'Pagamento',
        render: (row) => <StatusPill state={row.state} />,
    };
    const columns: DataColumn<OrganizerParticipant>[] = payments
        ? [
              name,
              quantity,
              {
                  key: 'value',
                  label: 'Valor',
                  render: (row) =>
                      row.amountCents == null ? '—' : money(row.amountCents),
              },
              state,
              phone,
          ]
        : [
              name,
              phone,
              quantity,
              {
                  key: 'date',
                  label: 'Data',
                  mobile: false,
                  render: (row) => (
                      <time dateTime={row.joinedAt}>
                          {maputoDate(row.joinedAt)}
                      </time>
                  ),
              },
              { ...state, desktop: false },
          ];
    return (
        <section className="organizer-console-panel organizer-participants">
            <p className="organizer-console-count">
                {rows.length} participantes ·{' '}
                {rows.reduce((sum, row) => sum + row.quantity, 0)}{' '}
                {view.campaign.unit}
            </p>
            <FilterBar>
                <form
                    className="organizer-filters"
                    onSubmit={(event) => event.preventDefault()}
                >
                    <label className="organizer-field">
                        Pesquisar nome
                        <input
                            className="junta-input"
                            type="search"
                            value={filters.search}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    search: event.target.value,
                                })
                            }
                        />
                    </label>
                    <label className="organizer-field">
                        Estado
                        <select
                            className="junta-input"
                            value={filters.state}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    state: event.target.value,
                                })
                            }
                        >
                            <option value="">Todos</option>
                            {states.map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button
                        type="button"
                        className="junta-btn junta-btn-md junta-btn-outline"
                        disabled={!rows.length}
                        onClick={exportCsv}
                    >
                        Exportar CSV ({rows.length})
                    </button>
                </form>
            </FilterBar>
            <DataTable
                records={rows}
                columns={columns}
                label="Participantes da campanha"
                rowKey={(row) => row.id}
                onRowClick={setNotes}
                empty={
                    <EmptyState
                        title={
                            participants.length
                                ? 'Sem resultados'
                                : 'Ainda não há participantes'
                        }
                        description={
                            participants.length
                                ? 'Ajuste a pesquisa ou o estado para ver os participantes.'
                                : 'As pessoas que registarem interesse nesta campanha aparecerão aqui.'
                        }
                        {...(participants.length
                            ? {
                                  onAction: () =>
                                      setFilters({ search: '', state: '' }),
                                  actionLabel: 'Limpar filtros',
                              }
                            : {})}
                    />
                }
            />
            <Dialog
                open={!!notes}
                onOpenChange={(open) => !open && setNotes(null)}
            >
                <DialogContent className="organizer-dialog max-h-[85svh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Observações de {notes?.name}</DialogTitle>
                        <DialogDescription>
                            Informação deixada pelo participante.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="whitespace-pre-wrap">
                        {notes?.notes || 'Sem observações.'}
                    </p>
                </DialogContent>
            </Dialog>
        </section>
    );
}
