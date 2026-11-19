import { useState } from 'react';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { SummaryCard } from '@/components/junta/OrganizerUi';
import { money } from '@/components/junta/PurchaseForms';
import {
    maputoDate,
    maputoInput,
    paymentExample,
} from '@/lib/organizer-presentation';
import { mutate, ValidationFailure, errorMessage } from '@/lib/server-mutation';
import { open } from '@/routes/organizador/compra';
import type { Purchase } from '@/types/purchase';
export function OpenPaymentForm({ purchase }: { purchase: Purchase }) {
    const offer = purchase.selectedProposal!;
    const [form, setForm] = useState({
        commissionPercent: '0',
        paymentDeadline: '',
        pickupPoint: '',
        deliveryZone: '',
        deliveryFee: '0',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const unit = Math.round(Number(offer.unit_price) * 100);
    const transport = offer.transport_included
        ? 0
        : (offer.transport_unit_cents ?? 0);
    const example = paymentExample(
        unit,
        transport,
        form.commissionPercent,
        form.deliveryFee,
    );
    const expires = [offer.valid_until, offer.reserved_until]
        .filter((value): value is string => !!value)
        .sort((a, b) => Date.parse(a) - Date.parse(b))[0];
    const field = (
        key: keyof typeof form,
        label: string,
        type = 'text',
        hint?: string,
    ) => (
        <label className="organizer-field" key={key}>
            {label}
            <input
                className="junta-input"
                type={type}
                value={form[key]}
                onChange={(event) =>
                    setForm({ ...form, [key]: event.target.value })
                }
                required={key !== 'deliveryZone'}
                min={type === 'number' ? 0 : undefined}
                max={
                    key === 'commissionPercent'
                        ? 100
                        : key === 'paymentDeadline'
                          ? maputoInput(expires)
                          : undefined
                }
                step={type === 'number' ? '0.01' : undefined}
                maxLength={
                    key === 'pickupPoint'
                        ? 500
                        : key === 'deliveryZone'
                          ? 255
                          : undefined
                }
                aria-invalid={!!errors[key]}
                aria-describedby={errors[key] ? `open-${key}` : undefined}
            />
            {hint && <small className="text-muted-foreground">{hint}</small>}
            {errors[key] && (
                <span
                    id={`open-${key}`}
                    role="alert"
                    className="text-destructive"
                >
                    {errors[key]}
                </span>
            )}
        </label>
    );
    const submit = async () => {
        setErrors({});
        if (
            form.paymentDeadline &&
            Date.parse(form.paymentDeadline + ':00+02:00') > Date.parse(expires)
        ) {
            setErrors({
                paymentDeadline:
                    'O prazo de pagamento tem de terminar enquanto as condições do fornecedor são válidas.',
            });
            return;
        }
        setProcessing(true);
        try {
            await mutate(open(purchase.id), form);
        } catch (failure) {
            setErrors(
                failure instanceof ValidationFailure
                    ? failure.errors
                    : { purchase: errorMessage(failure) },
            );
        } finally {
            setProcessing(false);
        }
    };
    return (
        <div className="organizer-columns" id="current-task">
            <OperationalPanel
                title="Pedir pagamento aos participantes"
                emphasis="brand"
                description={`Mínimo de ${offer.minimum_quantity} unidades e ${offer.available_quantity} disponíveis.`}
            >
                <form
                    className="grid gap-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void submit();
                    }}
                >
                    {!!Object.keys(errors).length && (
                        <div role="alert" className="organizer-alert">
                            <ul>
                                {Object.entries(errors).map(
                                    ([key, message]) => (
                                        <li key={key}>{message}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}
                    <fieldset className="grid gap-4">
                        <legend className="mb-4 font-semibold">Valores</legend>
                        {field(
                            'commissionPercent',
                            'Comissão (%)',
                            'number',
                            'Percentagem aplicada ao valor do produto.',
                        )}
                    </fieldset>
                    <fieldset className="grid gap-4">
                        <legend className="mb-4 font-semibold">Prazo</legend>
                        {field(
                            'paymentDeadline',
                            'Data e hora limite · Maputo',
                            'datetime-local',
                            `Até ${maputoDate(expires)} · Maputo, quando terminam as condições garantidas pelo fornecedor.`,
                        )}
                    </fieldset>
                    <fieldset className="grid gap-4">
                        <legend className="mb-4 font-semibold">Entrega</legend>
                        {field('pickupPoint', 'Ponto de recolha')}
                        {field(
                            'deliveryZone',
                            'Zona de entrega (opcional)',
                            'text',
                            'Deixe vazio para disponibilizar apenas recolha.',
                        )}
                        {field(
                            'deliveryFee',
                            'Tarifa fixa por pedido entregue (MZN)',
                            'number',
                        )}
                    </fieldset>
                    <button
                        className="junta-btn junta-btn-md justify-self-start junta-btn-primary"
                        disabled={processing}
                    >
                        {processing
                            ? 'A abrir pagamentos…'
                            : 'Pedir pagamento aos participantes'}
                    </button>
                </form>
            </OperationalPanel>
            <aside className="grid content-start gap-4">
                <SummaryCard
                    title="Exemplo de 1 unidade"
                    items={[
                        { label: 'Produto', value: money(unit) },
                        {
                            label: 'Transporte por unidade',
                            value: money(transport),
                        },
                        {
                            label: 'Comissão',
                            value: example
                                ? money(example.commissionCents)
                                : 'Reveja os valores',
                        },
                        {
                            label: 'Total com recolha',
                            value: example ? money(example.pickupCents) : '—',
                        },
                        ...(form.deliveryZone
                            ? [
                                  {
                                      label: 'Total com entrega',
                                      value: example
                                          ? money(example.deliveryCents)
                                          : '—',
                                  },
                              ]
                            : []),
                    ]}
                />
                <p className="organizer-caption">
                    Pré-visualização para uma unidade. Cada pedido confirma a
                    quantidade e os valores finais antes de pagar. A tarifa de
                    entrega é cobrada uma vez por pedido.
                </p>
            </aside>
        </div>
    );
}
