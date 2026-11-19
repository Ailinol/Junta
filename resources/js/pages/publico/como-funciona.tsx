import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    ClipboardList,
    GitCompareArrows,
    PackageCheck,
    Search,
    Users,
} from 'lucide-react';
import {
    PublicCallToAction,
    publicContainer,
    PublicHeading,
    PublicHero,
} from '@/components/junta/PublicSections';
import { index } from '@/routes/campanhas';
import { fornecedores } from '@/routes/publico';
const steps = [
    {
        icon: Search,
        title: 'Escolha uma campanha',
        text: 'Consulte o produto, a localização, o prazo e as condições. Confirme que a oportunidade corresponde ao que precisa.',
        detail: 'Primeiro, conheça as condições.',
    },
    {
        icon: Users,
        title: 'Indique a quantidade',
        text: 'Entre na sua conta e registe a quantidade pretendida. Reveja os dados antes de confirmar a participação.',
        detail: 'A sua necessidade soma-se à procura.',
    },
    {
        icon: GitCompareArrows,
        title: 'As propostas são avaliadas',
        text: 'Os fornecedores apresentam as condições. O organizador compara preço, quantidade disponível, prazos e outras condições comerciais.',
        detail: 'O menor preço não decide sozinho.',
    },
    {
        icon: PackageCheck,
        title: 'Acompanhe a campanha',
        text: 'Consulte a sua participação, o estado da campanha e os próximos passos conhecidos. Uma proposta seleccionada ainda não significa uma compra concluída.',
        detail: 'Cada fase tem um significado.',
    },
];
const questions = [
    [
        'Participar significa que já paguei?',
        'Não. A participação regista o seu interesse e a quantidade pretendida. Não equivale a um pagamento nem a uma confirmação de entrega.',
    ],
    [
        'O preço apresentado é o preço final?',
        'Quando uma campanha apresenta um preço de referência, esse valor é uma estimativa. Consulte as condições da campanha e da proposta seleccionada para conhecer a informação disponível.',
    ],
    [
        'Quem escolhe o fornecedor?',
        'O organizador autorizado avalia as propostas. A comparação inclui as condições comerciais relevantes; a proposta mais barata não é seleccionada automaticamente.',
    ],
    [
        'Posso também organizar campanhas?',
        'Sim. Na sua área de consumidor, abra “Ser organizador” e envie a candidatura. Após aprovação administrativa, mantém a mesma conta e ganha o acesso de organizador.',
    ],
];
export default function HowItWorks() {
    return (
        <>
            <Head title="Como funciona">
                <meta
                    name="description"
                    content="Conheça as etapas de uma compra colectiva: descobrir, participar, comparar propostas e acompanhar a campanha."
                />
            </Head>
            <main>
                <PublicHero
                    eyebrow="Como funciona"
                    title={
                        <>
                            Da sua necessidade à{' '}
                            <span className="text-primary">
                                força do grupo.
                            </span>
                        </>
                    }
                    description="Uma campanha reúne o que várias pessoas precisam de comprar. O organizador acompanha a procura e avalia as propostas dos fornecedores."
                    visual={
                        <div className="rounded-3xl border border-border bg-card p-6 text-foreground shadow-sm sm:p-9">
                            <div className="mb-7 flex items-center gap-3">
                                <span className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-junta-accent">
                                    <Users className="size-6" />
                                </span>
                                <div>
                                    <p className="font-semibold">
                                        O percurso de uma campanha
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Pessoas, procura e propostas ligadas
                                        entre si.
                                    </p>
                                </div>
                            </div>
                            <ol className="space-y-3">
                                {[
                                    'Necessidades individuais',
                                    'Procura reunida numa campanha',
                                    'Comparação de propostas',
                                    'Acompanhamento dos próximos passos',
                                ].map((s, i) => (
                                    <li
                                        key={s}
                                        className={
                                            'flex items-center gap-4 rounded-xl border p-4 ' +
                                            (i === 1
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-primary/10 bg-junta-primary-soft')
                                        }
                                    >
                                        <span
                                            className={
                                                'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ' +
                                                (i === 1
                                                    ? 'bg-junta-accent text-primary'
                                                    : 'bg-card text-primary')
                                            }
                                        >
                                            {i + 1}
                                        </span>
                                        <span className="text-sm font-medium">
                                            {s}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    }
                >
                    <Link
                        href={index()}
                        className="junta-btn junta-btn-md junta-btn-primary"
                    >
                        Encontrar uma campanha
                        <ArrowRight className="size-4" />
                    </Link>
                    <a
                        href="#etapas"
                        className="junta-btn junta-btn-md junta-btn-outline bg-card"
                    >
                        Ver as etapas
                    </a>
                </PublicHero>
                <section
                    id="etapas"
                    className={publicContainer + ' scroll-mt-24 py-14 sm:py-20'}
                >
                    <PublicHeading
                        eyebrow="Passo a passo"
                        title="Saiba o que acontece em cada etapa."
                    />
                    <ol className="mt-9 grid gap-5 md:grid-cols-2">
                        {steps.map((s, i) => (
                            <li
                                key={s.title}
                                className="rounded-2xl border border-primary/15 bg-card p-6 sm:p-8"
                            >
                                <div className="mb-6 flex items-center justify-between">
                                    <s.icon className="size-7 text-primary" />
                                    <span className="text-3xl font-semibold text-primary/20">
                                        0{i + 1}
                                    </span>
                                </div>
                                <h2 className="text-xl font-semibold">
                                    {s.title}
                                </h2>
                                <p className="mt-3 text-sm leading-7 text-muted-foreground">
                                    {s.text}
                                </p>
                                <p className="mt-5 flex items-center gap-2 border-t border-primary/10 pt-4 text-xs font-semibold text-primary">
                                    <Check className="size-4 shrink-0" />
                                    {s.detail}
                                </p>
                            </li>
                        ))}
                    </ol>
                </section>
                <section className="border-y border-primary/10 bg-junta-primary-soft">
                    <div
                        className={
                            publicContainer +
                            ' grid gap-8 py-12 md:grid-cols-[.8fr_1.2fr] lg:gap-16'
                        }
                    >
                        <PublicHeading
                            eyebrow="Três papéis, um processo"
                            title="Cada pessoa sabe o que tem de fazer."
                        />
                        <div className="grid gap-3">
                            {[
                                [
                                    'Consumidor',
                                    'Escolhe campanhas, indica quantidades e acompanha as suas participações.',
                                ],
                                [
                                    'Organizador',
                                    'Estrutura campanhas, acompanha a procura e compara propostas.',
                                ],
                                [
                                    'Fornecedor',
                                    'Avalia oportunidades e apresenta condições de fornecimento.',
                                ],
                            ].map(([title, text]) => (
                                <article
                                    key={title}
                                    className="rounded-2xl border border-primary/10 bg-card p-5"
                                >
                                    <h3 className="font-semibold text-primary">
                                        {title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {text}
                                    </p>
                                </article>
                            ))}
                            <Link
                                href={fornecedores()}
                                className="inline-flex items-center gap-2 py-3 text-sm font-semibold text-primary"
                            >
                                Conhecer a área de fornecedores
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    </div>
                </section>
                <section
                    id="duvidas"
                    className={
                        publicContainer +
                        ' grid scroll-mt-24 gap-8 py-14 sm:py-20 lg:grid-cols-[.8fr_1.2fr] lg:gap-16'
                    }
                >
                    <div>
                        <PublicHeading
                            eyebrow="Perguntas frequentes"
                            title="Antes de se juntar."
                        />
                        <ClipboardList className="mt-7 size-10 text-primary/40" />
                    </div>
                    <div className="space-y-3">
                        {questions.map(([q, a]) => (
                            <details
                                key={q}
                                className="rounded-2xl border border-primary/15 bg-card p-5 sm:p-6"
                            >
                                <summary className="cursor-pointer text-sm leading-6 font-semibold marker:text-primary">
                                    {q}
                                </summary>
                                <p className="mt-4 border-t border-primary/10 pt-4 text-sm leading-7 text-muted-foreground">
                                    {a}
                                </p>
                            </details>
                        ))}
                    </div>
                </section>
                <PublicCallToAction title="Já sabe como funciona. Encontre a sua campanha." />
            </main>
        </>
    );
}
