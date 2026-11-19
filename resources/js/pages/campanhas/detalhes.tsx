import { Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { ParticipationFlow } from '@/components/junta/ParticipationFlow';
import { EmptyState } from '@/components/junta/EmptyState';
import {
    QuantityStepper,
    SegmentedControl,
    MobileActionBar,
    ConsumerBreadcrumbs,
} from '@/components/junta/ConsumerUi';
import { DeadlineLabel } from '@/components/junta/OrganizerUi';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { money } from '@/components/junta/PurchaseForms';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { campaignIdFromUrl } from '@/lib/campaign-route';
import { campaignUnit } from '@/lib/campaign-presentation';
import { humanDeadline } from '@/lib/consumer-presentation';
import { mutate } from '@/lib/server-mutation';
import { aderir, show as workspaceShow } from '@/routes/consumidor/campanhas';
import { show as tracking } from '@/routes/consumidor/acompanhamento';
import { checkout } from '@/routes/consumidor/pedidos';
import { index } from '@/routes/campanhas';
import { explorar } from '@/routes/consumidor';
import type { Purchase } from '@/types/purchase';
export function StickyBuyBox({ children }: { children: ReactNode }) {
    return (
        <aside className="consumer-buy-box consumer-panel" aria-label="Comprar">
            {children}
        </aside>
    );
}
export default function CampaignDetails({
    purchase,
}: {
    purchase?: Purchase | null;
}) {
    const page = usePage();
    const internal = page.url.startsWith('/consumidor/');
    const catalogue = internal ? explorar() : index();
    const { data } = usePrototypeStore();
    const campaign = data.campaigns.find(
        (c) => c.id === campaignIdFromUrl(page.url, 0),
    );
    const [quantity, setQuantity] = useState(
        purchase?.participation?.quantity ?? 1,
    );
    const [delivery, setDelivery] = useState('recolha');
    const [tab, setTab] = useState('description');
    const [interest, setInterest] = useState(false);
    if (!campaign)
        return (
            <main className="consumer-page">
                <Head title="Campanha indisponível" />
                <h1 className="sr-only">Campanha indisponível</h1>
                <EmptyState
                    title="Campanha indisponível"
                    actionHref={catalogue.url}
                    actionLabel="Explorar campanhas"
                />
            </main>
        );
    const existing = data.participations.find(
        (p) => p.campaignId === campaign.id && p.status !== 'cancelada',
    );
    const open =
        campaign.status === 'ativa' &&
        campaign.purchasePhase === 'interest' &&
        new Date(campaign.deadline).getTime() > Date.now();
    const config = purchase?.order?.conditions ?? purchase?.config;
    const confirmed = !!config;
    const total = confirmed
        ? money(
              (delivery === 'entrega'
                  ? purchase?.deliveryQuote?.total_cents
                  : purchase?.quote?.total_cents) ??
                  purchase?.order?.total_cents ??
                  0,
          )
        : campaign.estimatedPrice === undefined
          ? 'Por confirmar'
          : '≈ ' + money(Math.round(campaign.estimatedPrice * quantity * 100));
    const canPay =
        !!purchase?.view?.primaryAction &&
        purchase.view.primaryAction.code === 'pay' &&
        !!purchase.order;
    const actionLabel = !page.props.auth.user
        ? 'Entrar para participar'
        : canPay
          ? 'Pagar'
          : existing
            ? 'Ver a minha compra'
            : open
              ? 'Registar interesse'
              : 'Fechada';
    const action =
        !page.props.auth.user || !internal ? (
            <Link
                className="junta-btn junta-btn-lg junta-btn-primary"
                href={workspaceShow(campaign.id)}
            >
                {actionLabel}
            </Link>
        ) : canPay && purchase?.order ? (
            <Link
                className="junta-btn junta-btn-lg junta-btn-primary"
                href={checkout(purchase.order.id, { query: { delivery } })}
            >
                {actionLabel}
            </Link>
        ) : existing ? (
            <Link
                className="junta-btn junta-btn-lg junta-btn-primary"
                href={tracking(campaign.id)}
            >
                {actionLabel}
            </Link>
        ) : (
            <button
                type="button"
                className="junta-btn junta-btn-lg junta-btn-primary"
                disabled={!open}
                onClick={() => setInterest(true)}
            >
                {actionLabel}
            </button>
        );
    return (
        <main className="consumer-page consumer-public consumer-product-page">
            <Head title={campaign.title} />
            <ConsumerBreadcrumbs
                items={[
                    { label: 'Explorar', href: catalogue.url },
                    { label: 'Campanha' },
                ]}
            />
            <div className="consumer-product-grid">
                <div className="consumer-product-content">
                    <CampaignVisual
                        campaign={campaign}
                        categoryFallback
                        className="aspect-[4/3] rounded-xl"
                    />
                    <h1>{campaign.title}</h1>
                    <p className="consumer-muted">{campaign.location}</p>
                    <div
                        className="consumer-product-tabs"
                        role="tablist"
                        aria-label="Informação da campanha"
                    >
                        {[
                            ['description', 'Descrição'],
                            ['conditions', 'Condições'],
                            ['how', 'Como funciona'],
                        ].map(([key, label]) => (
                            <button
                                type="button"
                                role="tab"
                                key={key}
                                id={`tab-${key}`}
                                aria-controls={`panel-${key}`}
                                aria-selected={tab === key}
                                tabIndex={tab === key ? 0 : -1}
                                onKeyDown={(event) => {
                                    const keys = [
                                        'description',
                                        'conditions',
                                        'how',
                                    ];
                                    const position = keys.indexOf(tab);
                                    const next =
                                        event.key === 'ArrowRight'
                                            ? keys[(position + 1) % 3]
                                            : event.key === 'ArrowLeft'
                                              ? keys[(position + 2) % 3]
                                              : event.key === 'Home'
                                                ? keys[0]
                                                : event.key === 'End'
                                                  ? keys[2]
                                                  : null;
                                    if (next) {
                                        event.preventDefault();
                                        setTab(next);
                                        document
                                            .getElementById(`tab-${next}`)
                                            ?.focus();
                                    }
                                }}
                                onClick={() => setTab(key)}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <section
                        role="tabpanel"
                        id={`panel-${tab}`}
                        aria-labelledby={`tab-${tab}`}
                        tabIndex={0}
                    >
                        {tab === 'description' ? (
                            <p className="whitespace-pre-wrap">
                                {campaign.description}
                            </p>
                        ) : tab === 'conditions' ? (
                            <div className="grid gap-3">
                                <p>Organizado por {campaign.organizerName}.</p>
                                <p>
                                    {config
                                        ? `Mínimo de ${config.minimum_quantity} ${campaign.unit}. ${config.pickup_point}`
                                        : 'O preço final e os custos são confirmados antes de pagar.'}
                                </p>
                                <p>
                                    {config?.terms ??
                                        'O registo de interesse não gera cobrança.'}
                                </p>
                            </div>
                        ) : (
                            <ol className="consumer-how-it-works">
                                <li>
                                    <strong>1. Regista interesse</strong>
                                    <p>Escolhe a quantidade de que precisa.</p>
                                </li>
                                <li>
                                    <strong>2. Paga quando abrir</strong>
                                    <p>
                                        Confirma o preço e paga no seu
                                        telemóvel.
                                    </p>
                                </li>
                                <li>
                                    <strong>3. Levanta ou recebe</strong>
                                    <p>
                                        Avisamos quando a mercadoria estiver
                                        pronta.
                                    </p>
                                </li>
                            </ol>
                        )}
                    </section>
                    <p className="consumer-refund-promise">
                        <ShieldCheck
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        Se o mínimo não for atingido, devolvemos o valor total.
                    </p>
                </div>
                <StickyBuyBox>
                    <p className="consumer-price-label">
                        {confirmed ? 'Preço confirmado' : 'Preço estimado'}
                    </p>
                    <p className="consumer-buy-price">
                        {config
                            ? money(config.unit_cents)
                            : campaign.estimatedPrice === undefined
                              ? 'Por confirmar'
                              : '≈ ' +
                                money(
                                    Math.round(campaign.estimatedPrice * 100),
                                )}{' '}
                        <span>/ {campaignUnit(campaign.unit)}</span>
                    </p>
                    <QuantityStepper
                        value={quantity}
                        onChange={setQuantity}
                        unit={campaign.unit}
                        disabled={!!existing || !open}
                    />
                    {(campaign.deliveryMode === 'ambos' ||
                        purchase?.deliveryQuote) && (
                        <SegmentedControl
                            label="Modalidade"
                            value={delivery}
                            options={[
                                { value: 'recolha', label: 'Recolha' },
                                { value: 'entrega', label: 'Entrega' },
                            ]}
                            onChange={setDelivery}
                        />
                    )}
                    <p className="consumer-buy-total">
                        Total {confirmed ? '' : 'estimado'}
                        <strong>{total}</strong>
                    </p>
                    {purchase?.view?.deadline ? (
                        <DeadlineLabel
                            deadline={purchase.view.deadline}
                            compact
                        />
                    ) : (
                        open && (
                            <DeadlineLabel
                                deadline={humanDeadline(campaign.deadline)}
                                compact
                            />
                        )
                    )}
                    {!open && !existing && (
                        <p className="consumer-help">
                            As novas participações estão encerradas. Era
                            necessário registar interesse antes da abertura dos
                            pagamentos.
                        </p>
                    )}
                    <MobileActionBar>
                        <span>
                            <small>Total {confirmed ? '' : 'estimado'}</small>
                            <strong>{total}</strong>
                        </span>
                        {action}
                    </MobileActionBar>
                </StickyBuyBox>
            </div>
            <Dialog open={interest} onOpenChange={setInterest}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Registar interesse</DialogTitle>
                        <DialogDescription>{campaign.title}</DialogDescription>
                    </DialogHeader>
                    <ParticipationFlow
                        campaign={campaign}
                        initialQuantity={quantity}
                        onConfirm={(amount, notes) =>
                            mutate(aderir(campaign.id), {
                                quantity: amount,
                                notes,
                            })
                        }
                    />
                </DialogContent>
            </Dialog>
        </main>
    );
}
