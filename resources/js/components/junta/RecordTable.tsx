import { useRef, useState, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Search, X } from 'lucide-react';
import {
    canSortAdminColumn,
    sortAdminRecords,
    type AdminRecordSort,
} from '@/lib/admin-record-sorting';
import { EmptyState } from '@/components/junta/EmptyState';
import { DetailList } from '@/components/junta/Page';
import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type RecordColumn<T> = {
    label: string;
    render: (record: T) => ReactNode;
};

export function RecordTable<T extends { id: number }>({
    records,
    columns,
    searchText,
    label,
    filters = [],
    matchesFilter,
    details,
    visual,
    actions,
    searchPlaceholder = 'Pesquisar por nome ou localização',
    appearance = 'default',
}: {
    records: T[];
    columns: RecordColumn<T>[];
    searchText: (record: T) => string;
    label: string;
    searchPlaceholder?: string;
    appearance?: 'default' | 'management';
    filters?: { value: string; label: string }[];
    matchesFilter?: (record: T, filter: string) => boolean;
    details?: (record: T) => ReactNode;
    visual?: (record: T) => ReactNode;
    actions?: (record: T) => ReactNode;
}) {
    const { component } = usePage();
    const isAdmin = component.startsWith('admin/');
    const detailsTrigger = useRef<HTMLButtonElement | null>(null);
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState('');
    const [sort, setSort] = useState<AdminRecordSort | null>(null);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const selectedRecord = records.find((record) => record.id === selectedId);
    const filtered = records.filter(
        (record) =>
            searchText(record)
                .toLocaleLowerCase('pt')
                .includes(query.trim().toLocaleLowerCase('pt')) &&
            (!filter || !matchesFilter || matchesFilter(record, filter)),
    );
    const ordered = isAdmin
        ? sortAdminRecords(filtered, component, sort)
        : filtered;
    const sortable = columns.filter((column) =>
        canSortAdminColumn(component, column.label),
    );
    const clearFilters = () => {
        setQuery('');
        setFilter('');
    };
    return (
        <div className={isAdmin ? 'junta-admin-records' : 'contents'}>
            <div
                className={cn(
                    'mb-5 flex flex-col gap-3 rounded-xl border p-3 sm:flex-row',
                    appearance === 'management'
                        ? 'mb-4 border-border bg-card shadow-xs'
                        : 'border-border bg-card p-4',
                    isAdmin && 'junta-admin-record-toolbar',
                )}
            >
                <label className={isAdmin ? 'junta-admin-search' : 'contents'}>
                    {isAdmin && (
                        <span className="junta-admin-field-label">
                            Pesquisar nesta lista
                        </span>
                    )}
                    <span className={isAdmin ? 'relative block' : 'contents'}>
                        {isAdmin && (
                            <Search
                                className="junta-admin-search-icon size-4"
                                aria-hidden="true"
                            />
                        )}
                        <input
                            type="search"
                            aria-label={`Pesquisar ${label}`}
                            placeholder={searchPlaceholder}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            className="junta-input min-w-0 flex-1"
                        />
                    </span>
                </label>
                {filters.length > 0 && (
                    <label
                        className={isAdmin ? 'junta-admin-filter' : 'contents'}
                    >
                        {isAdmin && (
                            <span className="junta-admin-field-label">
                                {label === 'fornecedores'
                                    ? 'Verificação'
                                    : label === 'utilizadores'
                                      ? 'Acesso'
                                      : 'Estado'}
                            </span>
                        )}
                        <select
                            className="junta-input sm:w-52"
                            aria-label={`Filtrar ${label}`}
                            value={filter}
                            onChange={(event) => setFilter(event.target.value)}
                        >
                            <option value="">Mostrar todos</option>
                            {filters.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                {isAdmin && (query || filter) && (
                    <button
                        type="button"
                        className="junta-admin-clear-filters junta-btn junta-btn-sm junta-btn-outline"
                        onClick={clearFilters}
                    >
                        <X className="size-4" aria-hidden="true" />
                        Limpar filtros
                    </button>
                )}
            </div>
            {isAdmin && sortable.length > 0 && (
                <label className="junta-admin-mobile-sort">
                    <span className="junta-admin-field-label">Ordenar por</span>
                    <select
                        className="junta-input"
                        value={sort ? `${sort.column}|${sort.direction}` : ''}
                        onChange={(event) => {
                            const [column, direction] =
                                event.target.value.split('|');
                            setSort(
                                column
                                    ? {
                                          column,
                                          direction: direction as
                                              | 'asc'
                                              | 'desc',
                                      }
                                    : null,
                            );
                        }}
                    >
                        <option value="">Ordem recebida</option>
                        {sortable.flatMap((column) => [
                            <option
                                key={`${column.label}-asc`}
                                value={`${column.label}|asc`}
                            >
                                {column.label} · crescente
                            </option>,
                            <option
                                key={`${column.label}-desc`}
                                value={`${column.label}|desc`}
                            >
                                {column.label} · decrescente
                            </option>,
                        ])}
                    </select>
                </label>
            )}
            <p role="status" className="mb-3 text-xs text-muted-foreground">
                {filtered.length} de {records.length}{' '}
                {isAdmin ? label : 'registos'}
                {isAdmin && sort && (
                    <span className="sr-only">
                        . {sort.column}: ordem{' '}
                        {sort.direction === 'asc' ? 'crescente' : 'decrescente'}
                        .
                    </span>
                )}
            </p>
            {filtered.length ? (
                <>
                    <div
                        className={cn(
                            'hidden overflow-x-auto border bg-card xl:block',
                            appearance === 'management'
                                ? 'rounded-2xl border-border shadow-xs'
                                : 'rounded-2xl border-border',
                            isAdmin && 'junta-admin-record-table',
                        )}
                        tabIndex={0}
                        role="region"
                        aria-label={`${label}: tabela com deslocação horizontal`}
                    >
                        <table className="junta-workspace-table w-full min-w-[620px] text-left text-sm">
                            <caption className="sr-only">{label}</caption>
                            <thead
                                className={
                                    appearance === 'management'
                                        ? 'border-b border-border bg-muted/60 text-muted-foreground'
                                        : 'border-b border-border bg-muted/50 text-muted-foreground'
                                }
                            >
                                <tr>
                                    {columns.map((column) => (
                                        <th
                                            key={column.label}
                                            scope="col"
                                            className="min-w-32 px-5 py-4 text-xs font-medium first:min-w-60"
                                            aria-sort={
                                                isAdmin &&
                                                canSortAdminColumn(
                                                    component,
                                                    column.label,
                                                )
                                                    ? sort?.column ===
                                                      column.label
                                                        ? sort.direction ===
                                                          'asc'
                                                            ? 'ascending'
                                                            : 'descending'
                                                        : 'none'
                                                    : undefined
                                            }
                                        >
                                            {isAdmin &&
                                            canSortAdminColumn(
                                                component,
                                                column.label,
                                            ) ? (
                                                <button
                                                    type="button"
                                                    className="junta-admin-sort-button"
                                                    aria-label={`${column.label}: ${sort?.column !== column.label ? 'ordenar por ordem crescente' : sort.direction === 'asc' ? 'ordenar por ordem decrescente' : 'repor a ordem recebida'}`}
                                                    onClick={() =>
                                                        setSort(
                                                            sort?.column !==
                                                                column.label
                                                                ? {
                                                                      column: column.label,
                                                                      direction:
                                                                          'asc',
                                                                  }
                                                                : sort.direction ===
                                                                    'asc'
                                                                  ? {
                                                                        ...sort,
                                                                        direction:
                                                                            'desc',
                                                                    }
                                                                  : null,
                                                        )
                                                    }
                                                >
                                                    {column.label}
                                                    {sort?.column ===
                                                    column.label ? (
                                                        sort.direction ===
                                                        'asc' ? (
                                                            <ArrowUp aria-hidden="true" />
                                                        ) : (
                                                            <ArrowDown aria-hidden="true" />
                                                        )
                                                    ) : (
                                                        <ArrowUpDown aria-hidden="true" />
                                                    )}
                                                </button>
                                            ) : (
                                                column.label
                                            )}
                                        </th>
                                    ))}
                                    {details && (
                                        <th
                                            scope="col"
                                            className="px-5 py-4 text-xs font-medium"
                                        >
                                            Detalhes
                                        </th>
                                    )}
                                    {actions && (
                                        <th
                                            scope="col"
                                            className="px-5 py-4 text-xs font-medium"
                                        >
                                            Acções
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {ordered.map((record) => (
                                    <tr
                                        key={record.id}
                                        className="align-top hover:bg-muted/30"
                                    >
                                        {columns.map((column, columnIndex) => (
                                            <td
                                                key={column.label}
                                                className="min-w-32 px-5 py-4 leading-6 first:min-w-60"
                                            >
                                                {columnIndex === 0 && visual ? (
                                                    <div className="flex items-start gap-3">
                                                        {visual(record)}
                                                        <div className="min-w-0">
                                                            {column.render(
                                                                record,
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    column.render(record)
                                                )}
                                            </td>
                                        ))}
                                        {details && (
                                            <td className="min-w-52 px-5 py-4">
                                                <button
                                                    type="button"
                                                    onClick={(event) => {
                                                        detailsTrigger.current =
                                                            event.currentTarget;
                                                        setSelectedId(
                                                            record.id,
                                                        );
                                                    }}
                                                    className="junta-btn junta-btn-sm junta-btn-outline"
                                                    aria-haspopup="dialog"
                                                >
                                                    Consultar
                                                </button>
                                            </td>
                                        )}
                                        {actions && (
                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {actions(record)}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <ul
                        aria-label={label}
                        className={cn(
                            'grid gap-4 xl:hidden',
                            appearance === 'management' && 'md:grid-cols-2',
                            isAdmin && 'junta-admin-record-cards',
                        )}
                    >
                        {ordered.map((record) => (
                            <li
                                key={record.id}
                                className={cn(
                                    'min-w-0 border p-5',
                                    appearance === 'management'
                                        ? 'rounded-2xl border-border bg-card shadow-xs'
                                        : 'rounded-2xl border-border bg-card',
                                )}
                            >
                                <dl
                                    className={
                                        appearance === 'management'
                                            ? 'grid grid-cols-2 gap-x-5 gap-y-4'
                                            : 'divide-y divide-primary/15'
                                    }
                                >
                                    {columns.map((column, columnIndex) => (
                                        <div
                                            key={column.label}
                                            data-record-field={column.label}
                                            className={
                                                appearance === 'management'
                                                    ? 'grid min-w-0 gap-1.5 first:col-span-2 first:border-b first:border-border first:pb-4 last:col-span-2 last:border-t last:border-border last:pt-4'
                                                    : 'grid gap-1 py-3 first:pt-0 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-4'
                                            }
                                        >
                                            <dt className="text-xs font-medium text-muted-foreground">
                                                {column.label}
                                            </dt>
                                            <dd className="min-w-0 text-sm leading-6 break-words">
                                                {columnIndex === 0 && visual ? (
                                                    <div className="flex items-start gap-3">
                                                        {visual(record)}
                                                        <div className="min-w-0">
                                                            {column.render(
                                                                record,
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    column.render(record)
                                                )}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {details && (
                                    <button
                                        type="button"
                                        onClick={(event) => {
                                            detailsTrigger.current =
                                                event.currentTarget;
                                            setSelectedId(record.id);
                                        }}
                                        className="junta-btn mt-3 junta-btn-sm junta-btn-outline"
                                        aria-haspopup="dialog"
                                    >
                                        Consultar detalhes
                                    </button>
                                )}
                                {actions && (
                                    <div className="mt-3 flex flex-wrap gap-2 border-t border-primary/15 pt-3">
                                        {actions(record)}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <EmptyState
                    className={isAdmin ? 'junta-admin-record-empty' : undefined}
                    title={
                        records.length
                            ? 'Sem registos para mostrar'
                            : isAdmin && label === 'candidaturas'
                              ? 'Ainda não há candidaturas'
                              : 'Ainda não existem registos'
                    }
                    description={
                        records.length
                            ? 'Ajuste a pesquisa ou os filtros.'
                            : isAdmin && label === 'candidaturas'
                              ? 'Os pedidos para organizar campanhas aparecerão aqui. Actualize a lista para verificar se chegaram novos pedidos.'
                              : 'Os registos guardados aparecerão nesta área.'
                    }
                    action={
                        query || filter
                            ? {
                                  label: 'Limpar filtros',
                                  onClick: clearFilters,
                              }
                            : isAdmin
                              ? {
                                    label: 'Actualizar lista',
                                    onClick: () => router.reload(),
                                }
                              : undefined
                    }
                />
            )}
            {details && (
                <Dialog
                    open={!!selectedRecord}
                    onOpenChange={(open) => {
                        if (!open) setSelectedId(null);
                    }}
                >
                    <DialogContent
                        onCloseAutoFocus={(event) => {
                            if (isAdmin) {
                                event.preventDefault();
                                detailsTrigger.current?.focus();
                            }
                        }}
                        className={cn(
                            'max-h-[85svh] overflow-y-auto sm:max-w-2xl',
                            isAdmin && 'junta-admin-record-dialog',
                        )}
                    >
                        <DialogHeader>
                            <DialogTitle>Detalhes · {label}</DialogTitle>
                            <DialogDescription className="sr-only">
                                Informação completa do registo seleccionado.
                            </DialogDescription>
                        </DialogHeader>
                        {selectedRecord && (
                            <>
                                <DetailList
                                    items={columns.map((column) => ({
                                        label: column.label,
                                        value: column.render(selectedRecord),
                                    }))}
                                />
                                <div className="space-y-4 rounded-xl border border-primary/15 bg-junta-primary-soft p-4 text-sm leading-6 break-words">
                                    {details(selectedRecord)}
                                </div>
                                {actions && (
                                    <div className="flex flex-wrap gap-2">
                                        {actions(selectedRecord)}
                                    </div>
                                )}
                            </>
                        )}
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
