import { useRemember } from '@inertiajs/react';
import { Page } from '@/components/junta/Page';
import { EmptyState } from '@/components/junta/EmptyState';
import { OrderCard, SegmentedControl } from '@/components/junta/ConsumerUi';
import { explorar } from '@/routes/consumidor';
import type { ConsumerOrder } from '@/types/consumer';
export default function Orders({
    consumerOrders: orders,
    completed = false,
}: {
    consumerOrders: ConsumerOrder[];
    completed?: boolean;
}) {
    const [filter, setFilter] = useRemember(
        completed ? 'completed' : 'active',
        'consumer-orders-filter',
    );
    const [search, setSearch] = useRemember('', 'consumer-orders-search');
    const records = orders.filter(
        (order) =>
            (filter === 'all' ||
                (filter === 'completed'
                    ? order.state.isFinal
                    : !order.state.isFinal)) &&
            order.campaign.title
                .toLocaleLowerCase('pt')
                .includes(search.trim().toLocaleLowerCase('pt')),
    );
    return (
        <Page className="consumer-page" title="As minhas compras">
            <div className="consumer-orders-filters">
                <SegmentedControl
                    label="Filtrar compras"
                    value={filter}
                    options={[
                        { value: 'active', label: 'Em curso' },
                        { value: 'completed', label: 'Concluídas' },
                        { value: 'all', label: 'Todas' },
                    ]}
                    onChange={setFilter}
                />
                <input
                    className="junta-input"
                    type="search"
                    placeholder="Pesquisar compra"
                    aria-label="Pesquisar compra"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                />
            </div>
            {records.length ? (
                <div className="grid gap-3">
                    {records.map((order) => (
                        <OrderCard key={order.id} order={order} />
                    ))}
                </div>
            ) : (
                <EmptyState
                    title={
                        orders.length
                            ? 'Sem compras com estes filtros'
                            : 'Ainda não participou em nenhuma campanha'
                    }
                    description={
                        orders.length
                            ? 'Escolha outro filtro ou produto.'
                            : 'Encontre um produto e junte-se à compra em grupo.'
                    }
                    actionHref={explorar.url()}
                    actionLabel="Explorar"
                />
            )}
        </Page>
    );
}
