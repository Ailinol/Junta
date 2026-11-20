import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Page, DetailList } from '@/components/junta/Page';
import { EmptyState } from '@/components/junta/EmptyState';
import { money } from '@/components/junta/PurchaseForms';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { delivery, update } from '@/routes/admin/tarifas';
import type { PlatformPricing } from '@/types/purchase';

type CampaignTariff = {
    id: number;
    title: string;
    unit: string;
    location: string;
    pricing: PlatformPricing | null;
};

function FreightQuote({ campaign }: { campaign: CampaignTariff }) {
    const pricing = campaign.pricing;
    const form = useForm({
        deliveryTotal:
            pricing?.deliveryTotalCents == null
                ? ''
                : (pricing.deliveryTotalCents / 100).toFixed(2),
        reference: pricing?.deliveryReference ?? '',
    });
    if (!pricing) return null;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(delivery.url(campaign.id), { preserveScroll: true });
    };
    return (
        <section className="junta-surface-card space-y-5 p-5 sm:p-6">
            <div>
                <h2 className="text-lg font-semibold">{campaign.title}</h2>
                <p className="text-sm text-muted-foreground">
                    {campaign.location}
                </p>
            </div>
            <DetailList
                items={[
                    { label: 'Participantes', value: pricing.participantCount },
                    {
                        label: 'Quantidade interessada',
                        value: `${pricing.totalQuantity} ${campaign.unit}`,
                    },
                    {
                        label: 'Comissão da plataforma',
                        value: `${pricing.commissionPercent}% sobre os produtos`,
                    },
                ]}
            />
            {pricing.transportIncluded ? (
                <p className="rounded-lg bg-muted p-4 text-sm">
                    O fornecedor inclui o transporte até ao ponto de recolha. A
                    entrega adicional é zero.
                </p>
            ) : (
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor={`freight-${campaign.id}`}>
                            Custo total do transporte (MZN)
                        </Label>
                        <Input
                            id={`freight-${campaign.id}`}
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.deliveryTotal}
                            onChange={(event) =>
                                form.setData(
                                    'deliveryTotal',
                                    event.target.value,
                                )
                            }
                            required
                        />
                        <InputError message={form.errors.deliveryTotal} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`reference-${campaign.id}`}>
                            Referência da cotação
                        </Label>
                        <Input
                            id={`reference-${campaign.id}`}
                            value={form.data.reference}
                            maxLength={120}
                            onChange={(event) =>
                                form.setData('reference', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.reference} />
                    </div>
                    <p className="text-sm text-muted-foreground sm:col-span-2">
                        Confirme a cotação considerando origem, destino, peso e
                        veículo. O total será repartido pela quantidade de cada
                        participante e recebido pela plataforma para organizar a
                        entrega ao ponto único.
                    </p>
                    {Object.entries(form.errors)
                        .filter(
                            ([field]) =>
                                !['deliveryTotal', 'reference'].includes(field),
                        )
                        .map(([field, message]) => (
                            <InputError key={field} message={message} />
                        ))}
                    <div className="flex flex-wrap items-center gap-3 sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            Guardar cotação
                        </Button>
                        {pricing.deliveryTotalCents !== null && (
                            <span className="text-sm text-muted-foreground">
                                Total confirmado:{' '}
                                {money(pricing.deliveryTotalCents)}
                            </span>
                        )}
                        {form.recentlySuccessful && (
                            <span
                                role="status"
                                className="text-sm text-primary"
                            >
                                Cotação guardada.
                            </span>
                        )}
                    </div>
                </form>
            )}
        </section>
    );
}

export default function Tariffs({
    commissionPercent,
    campaigns,
}: {
    commissionPercent: string;
    campaigns: {
        data: CampaignTariff[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const form = useForm({ commissionPercent });
    return (
        <Page
            title="Tarifas da plataforma"
            description="Defina a comissão do Junta e confirme o transporte das próximas compras."
        >
            <div className="grid gap-6">
                <section className="junta-surface-card space-y-4 p-5 sm:p-6">
                    <h2 className="text-lg font-semibold">
                        Comissão sobre os produtos
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        A comissão aplica-se ao preço dos produtos multiplicado
                        pela quantidade. Só a administração altera a taxa. As
                        compras que já abriram pagamentos mantêm as condições
                        acordadas.
                    </p>
                    <form
                        className="flex flex-wrap items-end gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(update.url(), { preserveScroll: true });
                        }}
                    >
                        <div className="space-y-2">
                            <Label htmlFor="commission-percent">
                                Comissão (%)
                            </Label>
                            <Input
                                id="commission-percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={form.data.commissionPercent}
                                onChange={(event) =>
                                    form.setData(
                                        'commissionPercent',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <InputError
                                message={form.errors.commissionPercent}
                            />
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            Guardar comissão
                        </Button>
                        {form.recentlySuccessful && (
                            <span
                                role="status"
                                className="text-sm text-primary"
                            >
                                Taxa guardada para as próximas compras.
                            </span>
                        )}
                    </form>
                </section>
                <div>
                    <h2 className="text-xl font-semibold">
                        Transporte até ao ponto de recolha
                    </h2>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Campanhas com proposta selecionada, antes da abertura
                        dos pagamentos. Não é aplicada uma estimativa automática
                        de distância ou peso.
                    </p>
                </div>
                {campaigns.data.length ? (
                    campaigns.data.map((campaign) => (
                        <FreightQuote key={campaign.id} campaign={campaign} />
                    ))
                ) : (
                    <EmptyState
                        title="Sem cotações pendentes"
                        description="As campanhas aparecem aqui depois da seleção da proposta e antes de abrir os pagamentos."
                    />
                )}
                <div className="flex justify-between gap-4">
                    {campaigns.prev_page_url && (
                        <Button variant="outline" asChild>
                            <Link href={campaigns.prev_page_url}>Anterior</Link>
                        </Button>
                    )}
                    {campaigns.next_page_url && (
                        <Button variant="outline" asChild>
                            <Link href={campaigns.next_page_url}>Seguinte</Link>
                        </Button>
                    )}
                </div>
            </div>
        </Page>
    );
}
