type SortField = { path: string; kind: 'text' | 'number' | 'date' };
export type AdminRecordSort = { column: string; direction: 'asc' | 'desc' };

const fields: Record<string, Record<string, SortField>> = {
    'admin/candidaturas': {
        Candidato: { path: 'fullName', kind: 'text' },
        Enviada: { path: 'createdAt', kind: 'date' },
    },
    'admin/utilizadores/index': {
        Nome: { path: 'name', kind: 'text' },
        Email: { path: 'email', kind: 'text' },
        Registo: { path: 'createdAt', kind: 'date' },
    },
    'admin/fornecedores/index': {
        Empresa: { path: 'companyName', kind: 'text' },
        NUIT: { path: 'nuit', kind: 'text' },
    },
    'admin/campanhas/index': {
        Campanha: { path: 'title', kind: 'text' },
        Participantes: { path: 'participantCount', kind: 'number' },
        Prazo: { path: 'deadline', kind: 'date' },
    },
    'admin/propostas/index': {
        Campanha: { path: 'campaign.title', kind: 'text' },
        'Preço unitário': { path: 'unitPrice', kind: 'number' },
        'Quantidade disponível': { path: 'availableQuantity', kind: 'number' },
        Validade: { path: 'validUntil', kind: 'date' },
    },
    'admin/historico': {
        Autor: { path: 'actor', kind: 'text' },
        Data: { path: 'createdAt', kind: 'date' },
    },
};

const collator = new Intl.Collator('pt-MZ', {
    numeric: true,
    sensitivity: 'base',
});

export function canSortAdminColumn(component: string, column: string): boolean {
    return Object.hasOwn(fields[component] ?? {}, column);
}

function sortValue(record: unknown, field: SortField): string | number | null {
    let value = record;
    for (const key of field.path.split('.')) {
        if (typeof value !== 'object' || value === null) return null;
        value = (value as Record<string, unknown>)[key];
    }
    if (
        (typeof value !== 'string' && typeof value !== 'number') ||
        value === ''
    )
        return null;
    if (field.kind === 'text') return String(value);
    const numeric =
        field.kind === 'date' ? Date.parse(String(value)) : Number(value);
    return Number.isFinite(numeric) ? numeric : null;
}

export function sortAdminRecords<T>(
    records: readonly T[],
    component: string,
    sort: AdminRecordSort | null,
): T[] {
    const field = sort && fields[component]?.[sort.column];
    if (!sort || !field) return [...records];

    return [...records].sort((left, right) => {
        const a = sortValue(left, field);
        const b = sortValue(right, field);
        if (a === null) return b === null ? 0 : 1;
        if (b === null) return -1;
        const comparison =
            typeof a === 'number' && typeof b === 'number'
                ? a - b
                : collator.compare(String(a), String(b));
        return sort.direction === 'asc' ? comparison : -comparison;
    });
}
