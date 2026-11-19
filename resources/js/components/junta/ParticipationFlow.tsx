import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { QuantityStepper } from '@/components/junta/ConsumerUi';
import { ValidationFailure } from '@/lib/server-mutation';
import { quantityError } from '@/lib/selected-form-validation';
import { money } from '@/components/junta/PurchaseForms';
import { show as tracking } from '@/routes/consumidor/acompanhamento';
import { show as product } from '@/routes/consumidor/campanhas';
import type { Campaign, Participation } from '@/types/junta';
export function ParticipationFlow({
    campaign,
    existing,
    onConfirm,
    initialQuantity = 1,
}: {
    campaign: Campaign;
    existing?: Participation;
    onConfirm: (quantity: string, notes: string) => void | Promise<void>;
    initialQuantity?: number;
}) {
    const { auth } = usePage().props;
    const [quantity, setQuantity] = useState(initialQuantity);
    const [notes, setNotes] = useState('');
    const [saving, setSaving] = useState(false);
    const [done, setDone] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    if (done)
        return (
            <div role="status" className="grid gap-4">
                <h3 className="text-lg font-semibold">Interesse registado</h3>
                <p>Avisamos quando abrirem os pagamentos.</p>
                <Link
                    href={tracking(campaign.id)}
                    className="junta-btn junta-btn-md junta-btn-primary"
                >
                    Ver a minha compra
                </Link>
            </div>
        );
    if (existing)
        return (
            <Link
                href={tracking(campaign.id)}
                className="junta-btn junta-btn-md junta-btn-primary"
            >
                Ver a minha compra
            </Link>
        );
    if (!auth.user)
        return (
            <Link
                href={product(campaign.id)}
                className="junta-btn junta-btn-md junta-btn-primary"
            >
                Entrar para participar
            </Link>
        );
    return (
        <form
            className="grid gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                const message = quantityError(String(quantity));
                if (message) {
                    setErrors({ quantity: message });
                    return;
                }
                if (saving) return;
                setSaving(true);
                setErrors({});
                void Promise.resolve(onConfirm(String(quantity), notes))
                    .then(() => setDone(true))
                    .catch((failure: unknown) =>
                        setErrors(
                            failure instanceof ValidationFailure
                                ? failure.errors
                                : {
                                      form: 'Não foi possível registar o interesse. Tente novamente.',
                                  },
                        ),
                    )
                    .finally(() => setSaving(false));
            }}
        >
            <QuantityStepper
                value={quantity}
                onChange={setQuantity}
                unit={campaign.unit}
                disabled={saving}
            />
            {errors.quantity && (
                <p className="consumer-error" role="alert">
                    {errors.quantity}
                </p>
            )}
            <label className="consumer-field">
                Observações (opcional)
                <textarea
                    className="junta-input"
                    value={notes}
                    onChange={(event) => setNotes(event.target.value)}
                    maxLength={2000}
                    disabled={saving}
                />
                {errors.notes && (
                    <span role="alert" className="consumer-error">
                        {errors.notes}
                    </span>
                )}
            </label>
            <p className="consumer-help">
                {quantity} {campaign.unit}
                {campaign.estimatedPrice !== undefined &&
                    ` · Preço estimado: ${money(Math.round(campaign.estimatedPrice * quantity * 100))}`}
                <br />
                Registar interesse não gera cobrança.
            </p>
            {errors.form && (
                <p role="alert" className="consumer-error">
                    {errors.form}
                </p>
            )}
            <button
                type="submit"
                className="junta-btn junta-btn-lg junta-btn-primary"
                disabled={saving || !!quantityError(String(quantity))}
            >
                {saving ? 'A guardar…' : 'Confirmar interesse'}
            </button>
        </form>
    );
}
