import { Link, useForm, usePage } from '@inertiajs/react';
import { Page } from '@/components/junta/Page';
import { PhoneField } from '@/components/junta/ConsumerUi';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { edit as editAccount } from '@/routes/profile';
import { update } from '@/routes/consumidor/perfil';
export default function ConsumerProfile() {
    const { data } = usePrototypeStore();
    const { auth } = usePage().props;
    const form = useForm(data.consumerProfile);
    return (
        <Page
            className="consumer-page consumer-profile-page"
            title="Perfil e contactos"
        >
            <form
                className="grid gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(update.url(), { preserveScroll: true });
                }}
            >
                <section className="consumer-panel">
                    <header className="flex items-center justify-between gap-3">
                        <h2>Dados pessoais</h2>
                        <Link
                            className="consumer-text-link"
                            href={editAccount()}
                        >
                            Editar
                        </Link>
                    </header>
                    <p>{auth.user.name}</p>
                    <p className="consumer-muted">{auth.user.email}</p>
                </section>
                <section className="consumer-panel">
                    <h2>Contacto de pagamento</h2>
                    <PhoneField
                        value={form.data.phone}
                        onChange={(value) => form.setData('phone', value)}
                        error={form.errors.phone}
                        disabled={form.processing}
                        helper={false}
                    />
                </section>
                <section className="consumer-panel">
                    <h2>Morada de entrega</h2>
                    <label className="consumer-field">
                        Cidade / província
                        <input
                            className="junta-input"
                            autoComplete="address-level2"
                            value={form.data.city}
                            onChange={(event) =>
                                form.setData('city', event.target.value)
                            }
                            disabled={form.processing}
                            maxLength={255}
                            aria-invalid={!!form.errors.city}
                        />
                        {form.errors.city && (
                            <span className="consumer-error" role="alert">
                                {form.errors.city}
                            </span>
                        )}
                    </label>
                    <label className="consumer-field mt-4">
                        Morada ou bairro
                        <textarea
                            className="junta-input"
                            autoComplete="street-address"
                            value={form.data.address}
                            onChange={(event) =>
                                form.setData('address', event.target.value)
                            }
                            disabled={form.processing}
                            maxLength={500}
                            aria-invalid={!!form.errors.address}
                        />
                        {form.errors.address && (
                            <span className="consumer-error" role="alert">
                                {form.errors.address}
                            </span>
                        )}
                    </label>
                </section>
                <section className="consumer-panel">
                    <h2>Avisos</h2>
                    <p className="consumer-help">
                        Preferências guardadas para os avisos da sua conta.
                    </p>
                    {[
                        [
                            'notifProposal',
                            'Novas propostas',
                            'Novidades das campanhas em que participa.',
                        ],
                        [
                            'notifReminder',
                            'Prazos das compras',
                            'Lembretes antes do fecho.',
                        ],
                        [
                            'notifPickup',
                            'Pronta para levantar',
                            'Quando puder receber a sua mercadoria.',
                        ],
                    ].map(([key, label, description]) => (
                        <label className="consumer-switch-row" key={key}>
                            <span>
                                <strong>{label}</strong>
                                <small>{description}</small>
                            </span>
                            <input
                                type="checkbox"
                                role="switch"
                                checked={
                                    form.data[
                                        key as
                                            | 'notifProposal'
                                            | 'notifReminder'
                                            | 'notifPickup'
                                    ]
                                }
                                onChange={(event) =>
                                    form.setData(
                                        key as
                                            | 'notifProposal'
                                            | 'notifReminder'
                                            | 'notifPickup',
                                        event.target.checked,
                                    )
                                }
                                disabled={form.processing}
                            />
                            {form.errors[key as keyof typeof form.data] && (
                                <span role="alert" className="consumer-error">
                                    {form.errors[key as keyof typeof form.data]}
                                </span>
                            )}
                        </label>
                    ))}
                </section>
                <div className="consumer-profile-save">
                    <p role="status">
                        {form.recentlySuccessful
                            ? 'Alterações guardadas.'
                            : 'Guarde as suas alterações.'}
                    </p>
                    <button
                        type="submit"
                        className="junta-btn junta-btn-md junta-btn-primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'A guardar…' : 'Guardar'}
                    </button>
                </div>
            </form>
        </Page>
    );
}
