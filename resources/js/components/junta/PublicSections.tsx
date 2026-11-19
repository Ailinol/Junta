import { Link } from '@inertiajs/react';
import { ArrowUpRight, Check, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import { index } from '@/routes/campanhas';
export const publicContainer = 'mx-auto w-full max-w-7xl px-5 sm:px-8 lg:px-10';
export function PublicHeading({
    eyebrow,
    title,
    children,
}: {
    eyebrow: string;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="max-w-2xl">
            <p className="mb-3 text-xs font-bold tracking-[.18em] text-primary uppercase">
                {eyebrow}
            </p>
            <h2 className="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">
                {title}
            </h2>
            {children && (
                <p className="mt-4 text-base leading-7 text-muted-foreground">
                    {children}
                </p>
            )}
        </div>
    );
}
export function PublicHero({
    eyebrow,
    title,
    description,
    children,
    visual,
}: {
    eyebrow: string;
    title: ReactNode;
    description: string;
    children: ReactNode;
    visual: ReactNode;
}) {
    return (
        <section className="junta-public-hero relative isolate overflow-hidden bg-junta-primary-deep text-white">
            <div
                className={
                    publicContainer +
                    ' relative grid items-center gap-12 py-14 sm:py-20 lg:grid-cols-[1.05fr_1fr] lg:gap-16 lg:py-24'
                }
            >
                <div>
                    <span className="mb-7 inline-flex items-center gap-2.5 rounded-full border border-white/15 bg-white/5 px-3.5 py-2 text-[11px] font-medium text-white/80 sm:text-xs">
                        <span className="size-1.5 rounded-full bg-junta-accent" />
                        {eyebrow}
                    </span>
                    <h1 className="max-w-2xl text-[42px] leading-[1.06] font-semibold tracking-[-.055em] text-white sm:text-6xl xl:text-[70px]">
                        {title}
                    </h1>
                    <p className="mt-6 max-w-lg text-base leading-7 text-white/70 sm:text-lg sm:leading-8">
                        {description}
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-3">
                        {children}
                    </div>
                    <div className="mt-8 flex items-center gap-2 text-xs text-white/65">
                        <Check className="size-4 shrink-0" />
                        Compras colectivas em Moçambique
                    </div>
                </div>
                {visual}
            </div>
        </section>
    );
}
export function PublicProductVisual({
    supplier = false,
}: {
    supplier?: boolean;
}) {
    return (
        <div className="relative mx-auto w-full max-w-xl">
            <div className="grid grid-cols-[1.15fr_1fr] gap-3 rounded-[2rem] border border-white/20 bg-white/10 p-3 shadow-[0_24px_60px_-20px_#00000060] sm:gap-4 sm:p-4 lg:-rotate-2">
                <figure className="flex flex-col overflow-hidden rounded-2xl bg-[#E2ECDD] dark:bg-muted">
                    <img
                        src={
                            '/images/campaigns/' +
                            (supplier ? 'construction' : 'food') +
                            '-illustration.jpg'
                        }
                        alt={
                            supplier
                                ? 'Ilustração de materiais de construção'
                                : 'Ilustração de produtos alimentares'
                        }
                        className="aspect-[4/5] w-full flex-1 object-cover"
                        fetchPriority="high"
                    />
                    <figcaption className="p-3 text-xs font-semibold text-[#155b43] sm:p-4 dark:text-primary">
                        {supplier
                            ? 'Capacidade para fornecer'
                            : 'Necessidades do dia a dia'}
                    </figcaption>
                </figure>
                <div className="grid gap-3 sm:gap-4">
                    <figure className="overflow-hidden rounded-2xl bg-[#EDF2E9] dark:bg-muted">
                        <img
                            src={
                                '/images/campaigns/' +
                                (supplier ? 'office' : 'solar') +
                                '-illustration.jpg'
                            }
                            alt={
                                supplier
                                    ? 'Ilustração de material de escritório'
                                    : 'Ilustração de equipamento solar'
                            }
                            className="aspect-square w-full object-cover"
                        />
                        <figcaption className="px-3 py-2 text-xs text-primary">
                            {supplier
                                ? 'Condições comerciais claras'
                                : 'Diferentes categorias'}
                        </figcaption>
                    </figure>
                    <div className="flex flex-col justify-center rounded-2xl bg-junta-primary-deep p-4 text-white sm:p-5">
                        <Users className="mb-3 size-6 text-junta-accent" />
                        <p className="text-lg leading-snug font-semibold">
                            {supplier
                                ? 'Procura reunida. Novas oportunidades.'
                                : 'A força está em comprar juntos.'}
                        </p>
                        <span className="mt-3 text-xs text-white/70">
                            Consumidores · Organizadores · Fornecedores
                        </span>
                    </div>
                </div>
            </div>
            <p className="mt-5 text-right text-[11px] text-white/60">
                Imagens ilustrativas de categorias de produtos.
            </p>
        </div>
    );
}
export function PublicCallToAction({
    title = 'A próxima compra pode começar em conjunto.',
    children = 'Explore as campanhas e encontre uma oportunidade para si.',
}: {
    title?: string;
    children?: ReactNode;
}) {
    return (
        <section className={publicContainer + ' py-12 sm:py-16'}>
            <div className="relative overflow-hidden rounded-3xl bg-junta-primary-deep p-7 text-white sm:p-12">
                <div
                    className="absolute -top-20 -right-20 size-72 rounded-full border-[40px] border-white/5"
                    aria-hidden="true"
                />
                <div className="relative flex flex-col items-start justify-between gap-7 lg:flex-row lg:items-center">
                    <div className="max-w-2xl">
                        <p className="mb-3 text-xs font-bold tracking-[.16em] text-junta-accent uppercase">
                            Juntos compramos melhor
                        </p>
                        <h2 className="text-3xl leading-tight font-semibold tracking-tight sm:text-4xl">
                            {title}
                        </h2>
                        <p className="mt-4 max-w-xl text-sm leading-6 text-white/75">
                            {children}
                        </p>
                    </div>
                    <Link
                        href={index()}
                        className="junta-btn junta-btn-md shrink-0 junta-btn-lime"
                    >
                        Explorar campanhas
                        <ArrowUpRight className="size-4" />
                    </Link>
                </div>
            </div>
        </section>
    );
}
