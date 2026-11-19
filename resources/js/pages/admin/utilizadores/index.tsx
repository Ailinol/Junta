import { useState } from 'react';
import { Page, DetailList } from '@/components/junta/Page';
import { DataTable } from '@/components/junta/OrganizerUi';
import { ResourceDrawer } from '@/components/junta/ResourceDrawer';
import {
    ResourceFilters,
    LabelPill,
    matches,
} from '@/components/junta/AdminResources';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { maputoDate } from '@/lib/organizer-presentation';
import type { UserProfile } from '@/types/junta';
const labels: Record<string, string> = {
    consumidor: 'Consumidor',
    organizador: 'Organizador',
    fornecedor: 'Fornecedor',
    admin: 'Administrador',
};
export default function Users() {
    const { data } = usePrototypeStore();
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('');
    const [selected, setSelected] = useState<
        (UserProfile & { roles: string[] }) | null
    >(null);
    const roles = (user: UserProfile & { roles: string[] }) => [
        ...new Set([
            ...user.roles,
            ...(data.suppliers.some((s) => s.userId === user.id)
                ? ['fornecedor']
                : []),
        ]),
    ];
    return (
        <Page
            title="Utilizadores"
            description="Consulta das contas e dos perfis de acesso."
        >
            <section className="admin-panel">
                <ResourceFilters
                    search={search}
                    onSearch={setSearch}
                    placeholder="Nome ou email"
                    value={role}
                    onFilter={setRole}
                    options={Object.entries(labels).map(([value, label]) => ({
                        value,
                        label,
                    }))}
                />
                <DataTable
                    records={data.users.filter(
                        (u) =>
                            (!role || roles(u).includes(role)) &&
                            matches(search, u.name, u.email),
                    )}
                    rowKey={(u) => u.id}
                    onRowClick={setSelected}
                    label="Utilizadores"
                    columns={[
                        { key: 'name', label: 'Nome', render: (u) => u.name },
                        {
                            key: 'email',
                            label: 'Email',
                            render: (u) => u.email,
                        },
                        {
                            key: 'roles',
                            label: 'Perfis',
                            render: (u) => (
                                <div className="admin-role-labels">
                                    {roles(u).map((value) => (
                                        <LabelPill
                                            key={value}
                                            label={labels[value] ?? 'Conta'}
                                        />
                                    ))}
                                </div>
                            ),
                        },
                        {
                            key: 'date',
                            label: 'Registo',
                            render: (u) => maputoDate(u.createdAt),
                        },
                    ]}
                />
            </section>
            <p className="admin-muted">
                A aprovação de organizadores faz-se em Candidaturas.
            </p>
            {selected && (
                <ResourceDrawer
                    title={selected.name}
                    description="Conta de utilizador · apenas consulta"
                    onClose={() => setSelected(null)}
                >
                    <DetailList
                        items={[
                            { label: 'Email', value: selected.email },
                            {
                                label: 'Perfis',
                                value: roles(selected)
                                    .map((value) => labels[value])
                                    .join(' · '),
                            },
                            {
                                label: 'Registo',
                                value: maputoDate(selected.createdAt),
                            },
                            {
                                label: 'Fornecedor associado',
                                value:
                                    data.suppliers.find(
                                        (s) => s.userId === selected.id,
                                    )?.companyName ?? 'Sem associação',
                            },
                        ]}
                    />
                    <p className="admin-muted">
                        A aprovação de organizadores faz-se em Candidaturas.
                    </p>
                </ResourceDrawer>
            )}
        </Page>
    );
}
