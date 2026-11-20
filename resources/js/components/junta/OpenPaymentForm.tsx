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
    const pricing = purchase.pricing;
    const [form, setForm] = useState({
        paymentDeadline: '',
        pickupPoint: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const unit = Math.round(Number(offer.unit_price) * 100);
    const example = paymentExample(
        unit,
        0,
        pricing?.commissionPercent ?? '0',
        '0',
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
                required
                max={
                    key === 'paymentDeadline' ? maputoInput(expires) : undefined
                }
                maxLength={key === 'pickupPoint' ? 500 : undefined}
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
                    <div className="rounded-lg bg-muted p-4 text-sm">
                        <p>
                            Comissão definida pela plataforma:{' '}
                            {pricing?.commissionPercent ?? '—'}% sobre os
                            produtos.
                        </p>
                        <p className="mt-2">
                            {pricing?.transportIncluded
                                ? 'O fornecedor inclui o transporte até ao ponto de recolha. Não há cobrança adicional.'
                                : pricing?.ready
                                  ? `Transporte total confirmado: ${money(pricing.deliveryTotalCents ?? 0)}, repartido pela quantidade de cada participante.`
                                  : 'Aguarde a cotação do transporte pela administração antes de pedir pagamentos.'}
                        </p>
                    </div>
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
                    </fieldset>
                    <button
                        className="junta-btn junta-btn-md justify-self-start junta-btn-primary"
                        disabled={processing || !pricing?.ready}
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
                            label: 'Transporte total da compra',
                            value:
                                pricing?.deliveryTotalCents == null
                                    ? 'Aguardar cotação'
                                    : money(pricing.deliveryTotalCents),
                        },
                        {
                            label: 'Comissão por unidade',
                            value: example
                                ? money(example.commissionCents)
                                : 'Reveja os valores',
                        },
                        {
                            label: 'Produto e comissão por unidade',
                            value: example ? money(example.pickupCents) : '—',
                        },
                    ]}
                />
                <p className="organizer-caption">
                    A comissão acompanha a quantidade de cada pedido. O
                    transporte até ao ponto único é repartido pela quantidade de
                    cada participante, com o valor exato apresentado antes de
                    pagar.
                </p>
            </aside>
        </div>
    );
}
