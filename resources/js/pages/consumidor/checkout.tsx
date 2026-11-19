import { Head, Link } from '@inertiajs/react';
import {
    PurchaseCheckout,
    WaitingScreen,
    ResultScreen,
    TestPaymentNotice,
    PaymentTestTools,
    PaymentTechnicalDetails,
} from '@/components/junta/PurchaseCheckout';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { show as tracking } from '@/routes/consumidor/acompanhamento';
import { ConsumerBreadcrumbs } from '@/components/junta/ConsumerUi';
import { index as purchases } from '@/routes/consumidor/campanhas';
import type { Purchase } from '@/types/purchase';
export default function Checkout({
    purchase,
    retry,
}: {
    purchase: Purchase;
    retry: boolean;
}) {
    const { data } = usePrototypeStore();
    const campaign = data.campaigns.find((c) => c.id === purchase.id);
    const code = purchase.view?.code;
    const waiting = code === 'payment_pending';
    const success = ['paid', 'ready', 'completed'].includes(code ?? '');
    const failure =
        !waiting &&
        !success &&
        (!purchase.view?.primaryAction ||
            (!!purchase.confirmation?.canRetry && !retry));
    return (
        <main className="consumer-page consumer-payment-page">
            <Head
                title={
                    waiting
                        ? 'Confirme o pagamento'
                        : success
                          ? 'Pagamento recebido'
                          : 'Pagar compra'
                }
            />
            <TestPaymentNotice enabled={purchase.testTools} />
            {waiting ? (
                <WaitingScreen purchase={purchase} />
            ) : success || failure ? (
                <ResultScreen purchase={purchase} success={success} />
            ) : campaign ? (
                <>
                    <ConsumerBreadcrumbs
                        items={[
                            {
                                label: 'As minhas compras',
                                href: purchases.url(),
                            },
                            {
                                label: 'Compra',
                                href: tracking.url(purchase.id),
                            },
                            { label: 'Pagar' },
                        ]}
                    />
                    <h1>Pagar compra</h1>
                    <PurchaseCheckout
                        key={purchase.order?.id}
                        purchase={purchase}
                        campaign={campaign}
                    />
                </>
            ) : (
                <Link href={tracking(purchase.id)}>Ver a minha compra</Link>
            )}
            <PaymentTestTools purchase={purchase} />
            <PaymentTechnicalDetails purchase={purchase} />
        </main>
    );
}
