import { usePage, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Page, DetailList } from '@/components/junta/Page';
import {
    DataTable,
    ConfirmDialog,
    TechnicalDetails,
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
import { campaignCategories } from '@/lib/campaign-presentation';
import { store, update, destroy } from '@/routes/admin/fornecedores';
import type { SupplierProfile } from '@/types/junta';
const values = (s: SupplierProfile | null) => ({
    companyName: s?.companyName ?? '',
    nuit: s?.nuit ?? '',
    contactName: s?.contactName ?? '',
    email: s?.email ?? '',
    phone: s?.phone ?? '',
    location: s?.location ?? '',
    userId: s?.userId ?? '',
    categories: s?.categories ?? [],
    description: s?.description ?? '',
    verified: s?.verified ?? false,
});
function Verification({
    supplier,
    onClose,
}: {
    supplier: SupplierProfile;
    onClose: () => void;
}) {
    const form = useForm({ ...values(supplier), verified: !supplier.verified });
    return (
        <ConfirmDialog
            className="admin-dialog"
            open
            title={
                supplier.verified
                    ? 'Suspender verificação?'
                    : 'Verificar fornecedor?'
            }
            description={
                supplier.verified
                    ? 'O fornecedor deixa de poder apresentar novas propostas. As propostas e compras existentes mantêm o seu fluxo.'
                    : 'Confirma a revisão dos dados comerciais e permite ao fornecedor apresentar propostas.'
            }
            confirmLabel={
                supplier.verified
                    ? 'Suspender verificação'
                    : 'Verificar fornecedor'
            }
            processing={form.processing}
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
            onConfirm={() =>
                form.patch(update.url(supplier.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Verificação actualizada.');
                        onClose();
                    },
                })
            }
        >
            <p>{supplier.companyName}</p>
            {Object.entries(form.errors).map(([key, error]) => (
                <p role="alert" key={key} className="text-destructive">
                    {error}
                </p>
            ))}
        </ConfirmDialog>
    );
}
export default function Suppliers() {
    const { data } = usePrototypeStore();
    const page = usePage();
    const initialId = Number(
        new URLSearchParams(page.url.split('?')[1]).get('supplierId'),
    );
    const [selected, setSelected] = useState<SupplierProfile | null>(
        data.suppliers.find((s) => s.id === initialId) ?? null,
    );
    const [editing, setEditing] = useState<SupplierProfile | 'new' | null>(
        null,
    );
    const [removing, setRemoving] = useState<SupplierProfile | null>(null);
    const [verifying, setVerifying] = useState<SupplierProfile | null>(null);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('');
    const supplier = editing && editing !== 'new' ? editing : null;
    const fields: EditorField[] = [
        { key: 'companyName', label: 'Razão social', required: true },
        { key: 'nuit', label: 'NUIT', required: true },
        { key: 'contactName', label: 'Pessoa de contacto', required: true },
        {
            key: 'email',
            label: 'Email comercial',
            type: 'email',
            required: true,
        },
        { key: 'phone', label: 'Telefone', required: true },
        { key: 'location', label: 'Localização', required: true },
        {
            key: 'userId',
            label: 'Conta associada',
            type: 'select',
            options: data.users.map((u) => ({
                value: String(u.id),
                label: u.name + ' · ' + u.email,
            })),
            hint: 'Sem conta associada, o fornecedor não consegue aceder à sua área.',
        },
        { key: 'categories', label: 'Categorias', type: 'categories' },
        {
            key: 'description',
            label: 'Capacidade e condições',
            type: 'textarea',
        },
    ];
    const dependency =
        selected && data.proposals.some((p) => p.supplierId === selected.id);
    return (
        <Page
            title="Fornecedores"
            description="Dados comerciais, contas associadas e verificação."
            actions={
                <button
                    className="junta-btn junta-btn-md junta-btn-primary"
                    onClick={() => setEditing('new')}
                >
                    Adicionar fornecedor
                </button>
            }
        >
            <section className="admin-panel">
                <ResourceFilters
                    search={search}
                    onSearch={setSearch}
                    placeholder="Empresa, NUIT ou contacto"
                    value={filter}
                    onFilter={setFilter}
                    options={[
                        { value: 'pending', label: 'Por verificar' },
                        { value: 'verified', label: 'Verificados' },
                    ]}
                />
                <DataTable
                    records={[...data.suppliers]
                        .sort(
                            (a, b) =>
                                Number(a.verified) - Number(b.verified) ||
                                b.id - a.id,
                        )
                        .filter(
                            (s) =>
                                (!filter ||
                                    (filter === 'verified'
                                        ? s.verified
                                        : !s.verified)) &&
                                matches(
                                    search,
                                    s.companyName,
                                    s.nuit,
                                    s.contactName,
                                    s.email,
                                    s.location,
                                ),
                        )}
                    label="Fornecedores"
                    rowKey={(s) => s.id}
                    onRowClick={setSelected}
                    columns={[
                        {
                            key: 'company',
                            label: 'Empresa',
                            render: (s) => s.companyName,
                        },
                        { key: 'nuit', label: 'NUIT', render: (s) => s.nuit },
                        {
                            key: 'contact',
                            label: 'Contacto',
                            render: (s) => (
                                <div>
                                    {s.contactName}
                                    <small>{s.email}</small>
                                </div>
                            ),
                        },
                        {
                            key: 'account',
                            label: 'Conta associada',
                            render: (s) =>
                                data.users.find((u) => u.id === s.userId)
                                    ?.name ?? 'Sem conta',
                        },
                        {
                            key: 'verification',
                            label: 'Verificação',
                            render: (s) => (
                                <LabelPill
                                    label={
                                        s.verified
                                            ? 'Verificado'
                                            : 'Por verificar'
                                    }
                                    tone={s.verified ? 'success' : 'warning'}
                                />
                            ),
                        },
                    ]}
                />
            </section>
            {selected && (
                <ResourceDrawer
                    title={selected.companyName}
                    onClose={() => setSelected(null)}
                >
                    <LabelPill
                        label={
                            selected.verified ? 'Verificado' : 'Por verificar'
                        }
                        tone={selected.verified ? 'success' : 'warning'}
                    />
                    <DetailList
                        items={[
                            { label: 'NUIT', value: selected.nuit },
                            { label: 'Contacto', value: selected.contactName },
                            { label: 'Email', value: selected.email },
                            { label: 'Telefone', value: selected.phone },
                            { label: 'Localização', value: selected.location },
                            {
                                label: 'Conta associada',
                                value:
                                    data.users.find(
                                        (u) => u.id === selected.userId,
                                    )?.name ?? 'Sem conta',
                            },
                        ]}
                    />
                    {!selected.userId && (
                        <p className="admin-muted">
                            Sem conta associada, este fornecedor não consegue
                            aceder à área de fornecedor.
                        </p>
                    )}
                    <div className="admin-category-chips">
                        {selected.categories.map((category) => (
                            <span key={category}>
                                {campaignCategories[category]}
                            </span>
                        ))}
                    </div>
                    <p>{selected.description || 'Sem condições adicionais.'}</p>
                    <div className="admin-resource-actions">
                        <button
                            className="junta-btn junta-btn-md junta-btn-primary"
                            onClick={() => {
                                setVerifying(selected);
                                setSelected(null);
                            }}
                        >
                            {selected.verified
                                ? 'Suspender verificação'
                                : 'Verificar fornecedor'}
                        </button>
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
                            disabled={!!dependency}
                            onClick={() => {
                                setRemoving(selected);
                                setSelected(null);
                            }}
                        >
                            Remover
                        </button>
                    </div>
                    {dependency && (
                        <p className="admin-muted">
                            O fornecedor tem propostas associadas e não pode ser
                            removido.
                        </p>
                    )}
                    <TechnicalDetails>
                        <p>Fornecedor: {selected.id}</p>
                    </TechnicalDetails>
                </ResourceDrawer>
            )}
            {editing && (
                <ResourceEditor
                    title={
                        supplier ? 'Editar fornecedor' : 'Adicionar fornecedor'
                    }
                    fields={fields}
                    initial={values(supplier)}
                    route={supplier ? update(supplier.id) : store()}
                    onClose={() => setEditing(null)}
                />
            )}
            {removing && (
                <ResourceRemoval
                    label={removing.companyName}
                    route={destroy(removing.id)}
                    onClose={() => setRemoving(null)}
                />
            )}
            {verifying && (
                <Verification
                    supplier={verifying}
                    onClose={() => setVerifying(null)}
                />
            )}
        </Page>
    );
}
