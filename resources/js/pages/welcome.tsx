import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    ClipboardCheck,
    Handshake,
    Search,
    Users,
} from 'lucide-react';
import { CampaignCard } from '@/components/junta/CampaignCard';
import {
    PublicCallToAction,
    publicContainer,
    PublicHeading,
    PublicHero,
    PublicProductVisual,
} from '@/components/junta/PublicSections';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { index } from '@/routes/campanhas';
import { comoFunciona, fornecedores } from '@/routes/publico';
const steps = [
    {
        icon: Search,
        title: 'Encontre a sua campanha',
        text: 'Veja o que se pretende comprar, onde e até quando pode participar.',
    },
    {
        icon: Users,
        title: 'Junte a sua quantidade',
        text: 'Indique o que precisa. A sua participação contribui para a procura colectiva.',
    },
    {
        icon: ClipboardCheck,
        title: 'Acompanhe cada etapa',
        text: 'Consulte o estado da campanha e as condições que o organizador está a avaliar.',
    },
];
export default function Welcome() {
    const { data } = usePrototypeStore();
    const campaigns = data.campaigns
        .filter(
            (c) => c.status === 'ativa' && new Date(c.deadline) > new Date(),
        )
        .slice(0, 3);
    return (
        <>
            <Head title="Juntos compramos melhor">
                <meta
                    name="description"
                    content="Descubra campanhas de compras colectivas e oportunidades para fornecedores no Junta."
                />
            </Head>
            <main>
                <PublicHero
                    eyebrow="Menos compras isoladas. Mais oportunidades."
                    title={
                        <>
                            Juntos, a sua compra ganha{' '}
                            <span className="text-junta-accent">
                                mais força.
                            </span>
                        </>
                    }
                    description="O Junta reúne pessoas com a mesma necessidade de compra e aproxima-as de fornecedores. Mais procura organizada para negociar melhores condições."
                    visual={<PublicProductVisual />}
                >
                    <Link
                        href={index()}
                        className="junta-btn junta-btn-lg junta-btn-lime"
                    >
                        Explorar campanhas
                        <ArrowRight className="size-4" />
                    </Link>
                    <Link
                        href={comoFunciona()}
                        className="junta-btn junta-btn-lg junta-btn-outline-dark"
                    >
                        Conhecer o processo
                    </Link>
                </PublicHero>
                <section className={publicContainer + ' py-14 sm:py-20'}>
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <PublicHeading
                            eyebrow="Uma compra. Várias pessoas."
                            title="Uma forma mais organizada de comprar."
                        >
                            Da sua necessidade à avaliação das propostas, cada
                            pessoa tem um papel claro.
                        </PublicHeading>
                        <Link
                            href={comoFunciona()}
                            className="inline-flex items-center gap-2 text-sm font-semibold text-primary"
                        >
                            Como funciona
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <div className="mt-8 grid gap-5 md:grid-cols-3">
                        {steps.map((s, i) => (
                            <article
                                key={s.title}
                                className="rounded-2xl border border-primary/15 bg-card p-6 sm:p-7"
                            >
                                <div className="mb-8 flex items-center justify-between">
                                    <span className="flex size-12 items-center justify-center rounded-2xl bg-junta-primary-soft text-primary">
                                        <s.icon className="size-6" />
                                    </span>
                                    <span className="text-sm font-semibold text-primary/40">
                                        0{i + 1}
                                    </span>
                                </div>
                                <h3 className="text-lg font-semibold">
                                    {s.title}
                                </h3>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    {s.text}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>
                {campaigns.length > 0 && (
                    <section className="border-y border-primary/10 bg-junta-primary-soft">
                        <div className={publicContainer + ' py-14 sm:py-16'}>
                            <div className="flex flex-wrap items-end justify-between gap-5">
                                <PublicHeading
                                    eyebrow="Oportunidades em curso"
                                    title="Encontre a sua próxima campanha."
                                />
                                <Link
                                    href={index()}
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-primary"
                                >
                                    Ver todas
                                    <ArrowRight className="size-4" />
                                </Link>
                            </div>
                            <div className="mt-8 grid auto-rows-fr gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {campaigns.map((c) => (
                                    <CampaignCard key={c.id} campaign={c} />
                                ))}
                            </div>
                        </div>
                    </section>
                )}
                <section
                    className={
                        publicContainer +
                        ' grid items-center gap-8 py-14 sm:py-20 lg:grid-cols-2 lg:gap-16'
                    }
                >
                    <div className="rounded-3xl bg-junta-primary-soft p-6 sm:p-9">
                        <div className="flex items-center gap-3 border-b border-primary/15 pb-5">
                            <Handshake className="size-7 text-primary" />
                            <p className="text-lg font-semibold text-primary">
                                Cada lado contribui.
                            </p>
                        </div>
                        {[
                            {
                                icon: Users,
                                title: 'Consumidores',
                                text: 'Reúnem as quantidades de que precisam.',
                            },
                            {
                                icon: Boxes,
                                title: 'Fornecedores',
                                text: 'Apresentam as suas condições comerciais.',
                            },
                            {
                                icon: ClipboardCheck,
                                title: 'Organizadores',
                                text: 'Comparam propostas e conduzem a campanha.',
                            },
                        ].map((s) => (
                            <div
                                key={s.title}
                                className="mt-4 flex items-center gap-4 rounded-2xl border border-primary/10 bg-card p-4"
                            >
                                <s.icon className="size-5 shrink-0 text-primary" />
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        {s.title}
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {s.text}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div>
                        <PublicHeading
                            eyebrow="Também para quem fornece"
                            title="A sua oferta encontra uma procura organizada."
                        >
                            Conheça as quantidades reunidas, avalie as condições
                            da oportunidade e prepare uma proposta comercial.
                        </PublicHeading>
                        <p className="mt-5 text-sm leading-7 text-muted-foreground">
                            Preço, disponibilidade e prazo contam. A decisão
                            considera o conjunto das condições, sem escolher
                            automaticamente a proposta mais barata.
                        </p>
                        <Link
                            href={fornecedores()}
                            className="junta-btn mt-7 junta-btn-md junta-btn-primary"
                        >
                            Conhecer a área de fornecedores
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>
                <PublicCallToAction />
            </main>
        </>
    );
}
