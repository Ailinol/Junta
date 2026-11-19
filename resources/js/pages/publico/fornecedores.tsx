import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    Check,
    ClipboardCheck,
    FileText,
    MapPin,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import {
    PublicCallToAction,
    publicContainer,
    PublicHeading,
    PublicHero,
    PublicProductVisual,
} from '@/components/junta/PublicSections';
import { dashboard } from '@/routes/fornecedor';
import { comoFunciona } from '@/routes/publico';
import { create as supplierRegistration } from '@/routes/fornecedor/adesao';
export default function Suppliers() {
    const { auth } = usePage().props;
    const hasAccess = !!auth.permissions?.supplier;
    return (
        <>
            <Head title="Para fornecedores">
                <meta
                    name="description"
                    content="Conheça o Junta para fornecedores. Avalie procura agregada e apresente condições comerciais em compras colectivas."
                />
            </Head>
            <main>
                <PublicHero
                    eyebrow="Junta para fornecedores"
                    title={
                        <>
                            A sua capacidade.
                            <br />
                            Uma procura{' '}
                            <span className="text-primary">reunida.</span>
                        </>
                    }
                    description="Encontre necessidades de compra organizadas numa campanha. Avalie as quantidades, conheça as condições e apresente uma proposta que faça sentido para o seu negócio."
                    visual={<PublicProductVisual supplier />}
                >
                    <Link
                        href={hasAccess ? dashboard() : supplierRegistration()}
                        className="junta-btn junta-btn-md junta-btn-primary"
                    >
                        {hasAccess
                            ? 'Abrir área de fornecedor'
                            : 'Aderir como fornecedor'}
                        <ArrowRight className="size-4" />
                    </Link>
                    <a
                        href="#acesso"
                        className="junta-btn junta-btn-md junta-btn-outline bg-card"
                    >
                        Como obter acesso
                    </a>
                </PublicHero>
                <section className={publicContainer + ' py-14 sm:py-20'}>
                    <PublicHeading
                        eyebrow="Uma oportunidade mais clara"
                        title="Conheça a procura antes de responder."
                    >
                        Informação organizada para avaliar a sua capacidade de
                        fornecimento.
                    </PublicHeading>
                    <div className="mt-8 grid gap-5 md:grid-cols-3">
                        {[
                            {
                                icon: Boxes,
                                title: 'Quantidades reunidas',
                                text: 'Veja a procura agregada da campanha e compare-a com a quantidade que consegue fornecer.',
                            },
                            {
                                icon: MapPin,
                                title: 'Contexto da compra',
                                text: 'Consulte o produto, a localização e a modalidade de entrega ou recolha solicitada.',
                            },
                            {
                                icon: ClipboardCheck,
                                title: 'Condições em conjunto',
                                text: 'A sua proposta é avaliada pelo conjunto das condições comerciais, para além do preço.',
                            },
                        ].map((s) => (
                            <article
                                key={s.title}
                                className="rounded-2xl border border-primary/15 bg-card p-6 sm:p-7"
                            >
                                <span className="mb-7 flex size-12 items-center justify-center rounded-2xl bg-junta-primary-soft text-primary">
                                    <s.icon className="size-6" />
                                </span>
                                <h2 className="text-lg font-semibold">
                                    {s.title}
                                </h2>
                                <p className="mt-3 text-sm leading-7 text-muted-foreground">
                                    {s.text}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>
                <section className="border-y border-primary/10 bg-junta-primary-soft">
                    <div
                        className={
                            publicContainer +
                            ' grid items-center gap-9 py-14 sm:py-16 lg:grid-cols-2 lg:gap-16'
                        }
                    >
                        <div>
                            <PublicHeading
                                eyebrow="Prepare uma resposta completa"
                                title="Uma boa proposta vai além do preço."
                            >
                                Apresente as condições que o organizador precisa
                                de compreender para tomar uma decisão.
                            </PublicHeading>
                            <p className="mt-5 text-sm leading-7 text-muted-foreground">
                                Os custos adicionais e as condições de pagamento
                                podem ser esclarecidos nas observações. O total
                                declarado deve ser interpretado com essas
                                condições.
                            </p>
                            <Link
                                href={comoFunciona()}
                                className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary"
                            >
                                Conhecer o processo de compra
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                        <div className="rounded-3xl border border-primary/15 bg-card p-6 sm:p-8">
                            <div className="mb-6 flex items-center gap-3 border-b border-primary/10 pb-5">
                                <FileText className="size-6 text-primary" />
                                <h3 className="text-lg font-semibold">
                                    O que incluir na proposta
                                </h3>
                            </div>
                            <ul className="grid gap-4 sm:grid-cols-2">
                                {[
                                    'Preço unitário',
                                    'Quantidade disponível',
                                    'Prazo de fornecimento',
                                    'Validade da proposta',
                                    'Entrega ou recolha',
                                    'Outras condições comerciais',
                                ].map((s) => (
                                    <li
                                        key={s}
                                        className="flex items-center gap-3 rounded-xl bg-junta-primary-soft p-4 text-sm"
                                    >
                                        <Check className="size-4 shrink-0 text-primary" />
                                        {s}
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-6 flex items-start gap-3 text-xs leading-6 text-muted-foreground">
                                <Truck className="mt-1 size-4 shrink-0 text-primary" />
                                Reveja os dados antes da submissão. Uma proposta
                                enviada não significa que tenha sido
                                seleccionada.
                            </p>
                        </div>
                    </div>
                </section>
                <section
                    id="acesso"
                    className={publicContainer + ' scroll-mt-24 py-14 sm:py-20'}
                >
                    <div className="grid gap-9 lg:grid-cols-[.9fr_1.1fr] lg:gap-16">
                        <div>
                            <PublicHeading
                                eyebrow="Acesso à plataforma"
                                title="Um perfil comercial, ligado à sua conta."
                            />
                            <p className="mt-5 text-sm leading-7 text-muted-foreground">
                                Crie um perfil comercial ligado à sua conta. A
                                administração verifica os dados antes de
                                permitir a apresentação de propostas.
                            </p>
                            <div className="mt-6 flex items-start gap-3 rounded-2xl border border-primary/15 bg-junta-primary-soft p-5">
                                <ShieldCheck className="mt-1 size-5 shrink-0 text-primary" />
                                <p className="text-sm leading-6 text-primary">
                                    Só fornecedores verificados podem submeter
                                    propostas. Ser organizador é um acesso
                                    diferente, com candidatura própria na área
                                    do consumidor.
                                </p>
                            </div>
                        </div>
                        <ol className="space-y-4">
                            {[
                                [
                                    'Associação da conta',
                                    'Entre na sua conta e envie os dados da empresa, do contacto e das categorias que pode fornecer.',
                                ],
                                [
                                    'Verificação administrativa',
                                    'A administração gere o estado de verificação do fornecedor.',
                                ],
                                [
                                    'Acesso às oportunidades',
                                    'Depois da verificação, escolha Fornecedor no menu Áreas. Pode preparar, rever e retirar as suas propostas enquanto a campanha aceita respostas.',
                                ],
                            ].map(([title, text], i) => (
                                <li
                                    key={title}
                                    className="flex items-start gap-4 rounded-2xl border border-primary/15 bg-card p-5 sm:p-6"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-semibold text-junta-accent">
                                        0{i + 1}
                                    </span>
                                    <div>
                                        <h3 className="font-semibold">
                                            {title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                            {text}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>
                <PublicCallToAction title="Conheça o que as pessoas querem comprar.">
                    Explore as campanhas públicas para perceber o contexto das
                    compras colectivas no Junta.
                </PublicCallToAction>
            </main>
        </>
    );
}
