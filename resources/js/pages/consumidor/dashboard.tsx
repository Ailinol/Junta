import { Link } from '@inertiajs/react';
import { Page } from '@/components/junta/Page';
import { EmptyState } from '@/components/junta/EmptyState';
import { CampaignCard } from '@/components/junta/CampaignCard';
import { OrderCard } from '@/components/junta/ConsumerUi';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { explorar } from '@/routes/consumidor';
import { index as purchases } from '@/routes/consumidor/campanhas';
import type { ConsumerOrder } from '@/types/consumer';
export default function Home({
    consumerOrders: orders,
}: {
    consumerOrders: ConsumerOrder[];
}) {
    const { data } = usePrototypeStore();
    const pending = orders.filter((order) => order.state.primaryAction);
    const readyOrder = pending.find((order) => order.state.code === 'ready');
    const highlighted = pending.length
        ? [
              pending[0],
              ...(readyOrder && readyOrder.id !== pending[0].id
                  ? [readyOrder]
                  : []),
          ]
        : [];
    const recent = orders
        .filter((order) => !highlighted.some((task) => task.id === order.id))
        .slice(0, 3);
    const open = data.campaigns
        .filter(
            (c) =>
                c.status === 'ativa' &&
                c.purchasePhase === 'interest' &&
                new Date(c.deadline).getTime() > Date.now(),
        )
        .slice(0, 4);
    return (
        <Page className="consumer-page" title="Início">
            {pending.length ? (
                <section className="consumer-home-priority">
                    <h2>
                        {pending[0].state.code === 'ready'
                            ? 'A sua compra está pronta'
                            : `Tem ${pending.filter((o) => o.state.code === 'payment').length} ${pending.filter((o) => o.state.code === 'payment').length === 1 ? 'compra para pagar' : 'compras para pagar'}`}
                    </h2>
                    {highlighted.map((order) => (
                        <OrderCard key={order.id} order={order} priority />
                    ))}
                </section>
            ) : (
                <EmptyState
                    title="Está tudo em dia"
                    description="Avisamos quando a sua compra precisar de si."
                    actionHref={explorar.url()}
                    actionLabel="Explorar campanhas"
                />
            )}
            {recent.length > 0 && (
                <section className="consumer-section">
                    <header>
                        <h2>As minhas compras</h2>
                        <Link href={purchases()}>Ver todas</Link>
                    </header>
                    <div className="grid gap-3">
                        {recent.map((order) => (
                            <OrderCard key={order.id} order={order} />
                        ))}
                    </div>
                </section>
            )}
            {open.length > 0 && (
                <section className="consumer-section">
                    <header>
                        <h2>Campanhas abertas</h2>
                        <Link href={explorar()}>Explorar mais</Link>
                    </header>
                    <div className="consumer-catalogue-grid">
                        {open.map((c) => (
                            <CampaignCard key={c.id} campaign={c} />
                        ))}
                    </div>
                </section>
            )}
        </Page>
    );
}
