import type { ReactNode } from 'react';
import { FilterBar } from '@/components/junta/OrganizerConsole';
import { StatusPill } from '@/components/junta/StatusBadge';
export function ResourceFilters({
    search,
    onSearch,
    placeholder = 'Pesquisar',
    value,
    onFilter,
    options = [],
}: {
    search: string;
    onSearch: (value: string) => void;
    placeholder?: string;
    value?: string;
    onFilter?: (value: string) => void;
    options?: { value: string; label: string }[];
}) {
    return (
        <FilterBar>
            <input
                className="junta-input"
                aria-label={placeholder}
                placeholder={placeholder}
                value={search}
                onChange={(event) => onSearch(event.target.value)}
            />
            {onFilter && (
                <select
                    className="junta-input"
                    aria-label="Filtrar lista"
                    value={value}
                    onChange={(event) => onFilter(event.target.value)}
                >
                    <option value="">Todos</option>
                    {options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            )}
        </FilterBar>
    );
}
export function LabelPill({
    label,
    tone = 'neutral',
}: {
    label: string;
    tone?: 'neutral' | 'info' | 'warning' | 'success';
}) {
    return <StatusPill compact state={{ label, tone, icon: 'document' }} />;
}
export function DrawerSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section>
            <h3>{title}</h3>
            {children}
        </section>
    );
}
export const matches = (
    query: string,
    ...values: (string | number | null | undefined)[]
) =>
    values
        .join(' ')
        .toLocaleLowerCase()
        .includes(query.trim().toLocaleLowerCase());
