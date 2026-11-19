import { EmptyState } from '@/components/junta/EmptyState';
import { AiReviewPanel } from '@/components/junta/AiReviewPanel';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { DetailList, Notice, Page } from '@/components/junta/Page';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { campaignIdFromUrl } from '@/lib/campaign-route';
import { formatDate, formatMZN } from '@/lib/formatters';
import { index } from '@/routes/fornecedor/oportunidades';

import { usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { mutate, errorMessage, ValidationFailure } from '@/lib/server-mutation';
import { proposalReviewErrors } from '@/lib/selected-form-validation';
import {
    store as storeProposal,
    update as updateProposal,
} from '@/routes/fornecedor/propostas';
import type { Proposal } from '@/types/junta';
export default function CreateProposal() {
    const { editingProposalId } = usePage<{ editingProposalId?: number }>()
        .props;
    const { data } = usePrototypeStore();
    const proposal = data.proposals.find(
        (record) => record.id === editingProposalId,
    );
    return (
        <ProposalEditor key={editingProposalId ?? 'new'} proposal={proposal} />
    );
}
function ProposalEditor({ proposal }: { proposal?: Proposal }) {
    const { data } = usePrototypeStore();
    const { url } = usePage();
    const c = data.campaigns.find(
        (c) => c.id === (proposal?.campaignId ?? campaignIdFromUrl(url, 0)),
    );
    const [form, setForm] = useState({
        unitPrice: proposal ? String(proposal.unitPrice) : '',
        availableQuantity: proposal ? String(proposal.availableQuantity) : '',
        minimumQuantity:
            proposal?.minimumQuantity == null
                ? ''
                : String(proposal.minimumQuantity),
        reservedUntil: proposal?.reservedUntil?.slice(0, 10) ?? '',
        transportIncluded: proposal?.transportIncluded === false ? '0' : '1',
        transportUnitPrice:
            proposal?.transportUnitPrice == null
                ? '0'
                : String(proposal.transportUnitPrice),
        deliveryDays: proposal ? String(proposal.deliveryDays) : '',
        deliveryMode:
            proposal?.deliveryMode ??
            ('entrega' as 'entrega' | 'recolha' | 'ambos'),
        validUntil: proposal?.validUntil.slice(0, 10) ?? '',
        notes: proposal?.notes ?? '',
    });
    const [step, setStep] = useState<'form' | 'review'>('form');
    const [error, setError] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    if (!c)
        return (
            <Page title="Preparar proposta">
                <EmptyState
                    title="Escolha uma oportunidade"
                    description="Consulte as condições antes de preparar a proposta."
                    actionLabel="Ver oportunidades"
                    actionHref={index.url()}
                />
            </Page>
        );
    const open = data.supplierProfile.verified && c.acceptsProposals === true;
    const valid = Object.keys(proposalReviewErrors(form)).length === 0;
    const review = (e: FormEvent) => {
        e.preventDefault();
        const errors = proposalReviewErrors(form);
        setFieldErrors(errors);
        setError('');
        if (valid) {
            setError('');
            setStep('review');
        }
    };
    const submit = async () => {
        if (!valid || !open || step !== 'review' || saving) return;
        setSaving(true);
        setError('');
        setFieldErrors({});
        try {
            await mutate(
                proposal ? updateProposal(proposal.id) : storeProposal(),
                {
                    ...form,
                    transportIncluded: form.transportIncluded === '1',
                    campaignId: c.id,
                },
            );
        } catch (failure) {
            if (failure instanceof ValidationFailure) {
                setFieldErrors(failure.errors);
                setError(
                    Object.entries(failure.errors)
                        .filter(([key]) => !(key in form))
                        .map(([, message]) => message)
                        .join(' '),
                );
                setStep('form');
                return;
            }
            setError(errorMessage(failure));
        } finally {
            setSaving(false);
        }
    };
    const fields = [
        {
            key: 'minimumQuantity',
            label: 'Mínimo comercial (unidades)',
            type: 'number',
            step: '1',
        },
        {
            key: 'reservedUntil',
            label: 'Reserva válida até',
            type: 'date',
            step: undefined,
        },
        {
            key: 'transportUnitPrice',
            label: 'Transporte adicional até ao ponto por unidade (MZN)',
            type: 'number',
            step: '0.01',
        },
        {
            key: 'unitPrice',
            label: 'Preço unitário (MT)',
            type: 'number',
            step: '0.01',
        },
        {
            key: 'availableQuantity',
            label: 'Quantidade disponível (' + c.unit + ')',
            type: 'number',
            step: '1',
        },
        {
            key: 'deliveryDays',
            label: 'Prazo de fornecimento (dias)',
            type: 'number',
            step: '1',
        },
        {
            key: 'validUntil',
            label: 'Proposta válida até',
            type: 'date',
            step: undefined,
        },
    ] as const;
    return (
        <Page
            title={proposal ? 'Editar proposta' : 'Preparar proposta'}
            description={c.title}
            narrow
            back={{ href: index.url(), label: 'Oportunidades' }}
        >
            {!open ? (
                <Notice>
                    Esta oportunidade já não está aberta a novas respostas.
                </Notice>
            ) : (
                <OperationalPanel
                    title={
                        step === 'form'
                            ? 'Condições comerciais'
                            : 'Rever proposta'
                    }
                >
                    <p className="mb-5 text-sm text-muted-foreground">
                        Procura agregada: {c.currentQuantity} {c.unit} ·{' '}
                        {c.location}
                    </p>
                    {step === 'form' ? (
                        <form
                            onSubmit={review}
                            noValidate
                            className="space-y-5"
                        >
                            <label className="grid gap-2 text-sm">
                                Transporte ao ponto de recolha
                                <select
                                    className="junta-input"
                                    value={form.transportIncluded}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            transportIncluded:
                                                event.target.value,
                                            transportUnitPrice:
                                                event.target.value === '1'
                                                    ? '0'
                                                    : form.transportUnitPrice,
                                        })
                                    }
                                >
                                    <option value="1">
                                        Incluído no preço (adicional zero)
                                    </option>
                                    <option value="0">
                                        Cobrado por unidade à parte
                                    </option>
                                </select>
                            </label>
                            <div className="grid gap-5 sm:grid-cols-2">
                                {fields.map((f) => (
                                    <div key={f.key}>
                                        <label
                                            htmlFor={f.key}
                                            className="mb-2 block text-sm font-medium"
                                        >
                                            {f.label}
                                        </label>
                                        <input
                                            id={f.key}
                                            type={f.type}
                                            min={
                                                f.type === 'number'
                                                    ? f.key ===
                                                      'transportUnitPrice'
                                                        ? '0'
                                                        : f.key === 'unitPrice'
                                                          ? '0.01'
                                                          : '1'
                                                    : undefined
                                            }
                                            step={f.step}
                                            required
                                            disabled={
                                                saving ||
                                                (f.key ===
                                                    'transportUnitPrice' &&
                                                    form.transportIncluded ===
                                                        '1')
                                            }
                                            value={form[f.key]}
                                            onChange={(e) =>
                                                setForm({
                                                    ...form,
                                                    [f.key]: e.target.value,
                                                })
                                            }
                                            aria-invalid={!!fieldErrors[f.key]}
                                            aria-describedby={
                                                fieldErrors[f.key]
                                                    ? f.key + '-error'
                                                    : undefined
                                            }
                                            className="junta-input aria-invalid:border-destructive aria-invalid:ring-destructive/20"
                                        />
                                        {[
                                            'validUntil',
                                            'reservedUntil',
                                        ].includes(f.key) && (
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                O organizador só poderá abrir
                                                pagamentos até esta data.
                                            </p>
                                        )}
                                        {fieldErrors[f.key] && (
                                            <p
                                                id={f.key + '-error'}
                                                role="alert"
                                                className="mt-2 text-sm text-destructive"
                                            >
                                                {fieldErrors[f.key]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <div>
                                <label
                                    htmlFor="deliveryMode"
                                    className="mb-2 block text-sm font-medium"
                                >
                                    Modalidade de fornecimento
                                </label>
                                <select
                                    id="deliveryMode"
                                    className="junta-input"
                                    value={form.deliveryMode}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            deliveryMode: e.target
                                                .value as typeof form.deliveryMode,
                                        })
                                    }
                                >
                                    <option value="entrega">Entrega</option>
                                    <option value="recolha">Recolha</option>
                                    <option value="ambos">
                                        Entrega ou recolha
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    htmlFor="notes"
                                    className="mb-2 block text-sm font-medium"
                                >
                                    Outras condições comerciais
                                </label>
                                <textarea
                                    id="notes"
                                    className="junta-input min-h-28"
                                    value={form.notes}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            notes: e.target.value,
                                        })
                                    }
                                    placeholder="Custos adicionais, pagamento e condições de entrega, se aplicáveis."
                                />
                            </div>
                            {error && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            )}
                            <button className="junta-btn junta-btn-md junta-btn-primary">
                                Rever proposta
                            </button>
                        </form>
                    ) : (
                        <>
                            <DetailList
                                items={[
                                    {
                                        label: 'Mínimo comercial',
                                        value:
                                            form.minimumQuantity ||
                                            'Por confirmar',
                                    },
                                    {
                                        label: 'Reserva válida até',
                                        value: form.reservedUntil
                                            ? formatDate(form.reservedUntil)
                                            : 'Por confirmar',
                                    },
                                    {
                                        label: 'Transporte ao ponto por unidade',
                                        value:
                                            form.transportIncluded === '1'
                                                ? 'Incluído'
                                                : formatMZN(
                                                      Number(
                                                          form.transportUnitPrice,
                                                      ),
                                                  ),
                                    },
                                    {
                                        label: 'Fornecedor',
                                        value: data.supplierProfile.companyName,
                                    },
                                    {
                                        label: 'Preço unitário',
                                        value: formatMZN(
                                            Number(form.unitPrice),
                                        ),
                                    },
                                    {
                                        label: 'Quantidade disponível',
                                        value:
                                            form.availableQuantity +
                                            ' ' +
                                            c.unit,
                                    },
                                    {
                                        label: 'Total da quantidade proposta',
                                        value: formatMZN(
                                            Number(form.unitPrice) *
                                                Number(form.availableQuantity),
                                        ),
                                    },
                                    {
                                        label: 'Prazo de fornecimento',
                                        value: form.deliveryDays + ' dias',
                                    },
                                    {
                                        label: 'Modalidade',
                                        value:
                                            form.deliveryMode === 'ambos'
                                                ? 'Entrega ou recolha'
                                                : form.deliveryMode,
                                    },
                                    {
                                        label: 'Validade',
                                        value: formatDate(form.validUntil),
                                    },
                                ]}
                            />
                            {form.notes && (
                                <p className="mt-5 text-sm leading-6 whitespace-pre-wrap">
                                    {form.notes}
                                </p>
                            )}
                            <Notice>
                                A proposta será registada no servidor e
                                disponibilizada ao organizador autorizado.
                            </Notice>
                            {error && (
                                <p
                                    role="alert"
                                    className="mb-4 text-sm text-destructive"
                                >
                                    {error}
                                </p>
                            )}
                            <p className="my-4 text-sm text-muted-foreground">
                                Ao ser seleccionada, esta proposta passa a ser o
                                seu compromisso. Não será pedida nova
                                confirmação.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <button
                                    onClick={() => setStep('form')}
                                    className="junta-btn junta-btn-md junta-btn-outline"
                                >
                                    Corrigir dados
                                </button>
                                <button
                                    disabled={saving}
                                    onClick={() => {
                                        void submit();
                                    }}
                                    className="junta-btn junta-btn-md junta-btn-primary"
                                >
                                    Submeter proposta
                                </button>
                            </div>
                        </>
                    )}
                </OperationalPanel>
            )}
            {open && (
                <AiReviewPanel
                    kind="proposal"
                    payload={{
                        ...form,
                        campaignId: c.id,
                        transportIncluded: form.transportIncluded === '1',
                    }}
                    sourceVersion={JSON.stringify(c)}
                />
            )}
        </Page>
    );
}
