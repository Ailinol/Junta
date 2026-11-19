import { BrandLogo } from '@/components/junta/BrandLogo';
import AppearanceMenu from '@/components/appearance-menu';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="junta-auth grid min-h-svh bg-card lg:grid-cols-[1fr_1fr]">
            <aside className="junta-public-hero relative isolate hidden flex-col justify-between overflow-hidden bg-junta-primary-deep p-12 text-white lg:flex xl:p-16">
                <BrandLogo inverse href={home.url()} />
                <div className="relative max-w-lg py-12">
                    <p className="mb-5 text-xs font-medium tracking-[.18em] text-white/70 uppercase">
                        Compras colectivas
                    </p>
                    <h2 className="text-4xl leading-[1.15] font-medium tracking-[-.04em] text-white xl:text-[46px]">
                        Uma necessidade sua.
                        <br />
                        Uma oportunidade
                        <br />
                        para todos.
                    </h2>
                    <p className="mt-6 max-w-sm text-sm leading-7 text-white/75">
                        Encontre campanhas, junte a sua quantidade e acompanhe
                        cada etapa da compra.
                    </p>
                </div>
                <p className="text-xs text-white/60">
                    Juntos compramos melhor.
                </p>
            </aside>
            <div className="flex flex-col px-6 py-8 sm:px-12">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <BrandLogo compact className="lg:invisible" />
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-2 text-xs text-muted-foreground hover:text-primary"
                    >
                        <ArrowLeft className="size-4" />
                        Voltar ao Junta
                    </Link>
                    <AppearanceMenu />
                </div>
                <main className="mx-auto my-auto w-full max-w-sm py-12">
                    <header className="mb-8">
                        <h1 className="text-[28px] font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </header>
                    {children}
                </main>
                <p className="mt-8 text-center text-xs text-muted-foreground">
                    Junta · Compras colectivas
                </p>
            </div>
        </div>
    );
}
