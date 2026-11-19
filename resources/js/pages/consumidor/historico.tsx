import Orders from '@/pages/consumidor/campanhas/index';
import type { ConsumerOrder } from '@/types/consumer';
export default function History(props: { consumerOrders: ConsumerOrder[] }) {
    return <Orders {...props} completed />;
}
