import AppearanceMenu from '@/components/appearance-menu';
import { BrandLogo } from '@/components/junta/BrandLogo';
import { DemoDataNotice } from '@/components/junta/DemoDataNotice';
import { dashboard, home, login, register } from '@/routes';
import { index } from '@/routes/campanhas';
import { comoFunciona, fornecedores } from '@/routes/publico';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Menu, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
const links = [
    { label: 'Início', href: home.url() },
    { label: 'Campanhas', href: index.url() },
    { label: 'Como funciona', href: comoFunciona.url() },
    { label: 'Para fornecedores', href: fornecedores.url() },
];
export default function PublicLayout({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const {
        url,
        props: { auth },
    } = usePage();
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <a
                href="#public-content"
                className="sr-only focus:not-sr-only focus:bg-card focus:p-3"
            >
                Saltar para o conteúdo
            </a>
            <header
                className="sticky top-0 z-30 border-b border-border bg-card/95 backdrop-blur-md"
                onKeyDown={(event) => {
                    if (event.key === 'Escape') setOpen(false);
                }}
            >
                <div className="mx-auto flex min-h-20 w-full max-w-7xl items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
                    <BrandLogo compact href={home.url()} />
                    <nav
                        className="hidden items-center gap-7 lg:flex"
                        aria-label="Navegação principal"
                    >
                        {links.map((l) => (
                            <Link
                                key={l.label}
                                href={l.href}
                                aria-current={
                                    url.split(/[?#]/)[0] === l.href
                                        ? 'page'
                                        : undefined
                                }
                                className={
                                    url.split(/[?#]/)[0] === l.href
                                        ? 'text-sm font-semibold text-primary'
                                        : 'text-sm text-muted-foreground hover:text-primary'
                                }
                            >
                                {l.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="ml-auto lg:ml-0">
                        <AppearanceMenu />
                    </div>
                    <div className="hidden items-center gap-3 lg:flex">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="junta-btn junta-btn-sm junta-btn-primary"
                            >
                                Abrir aplicação
                                <ArrowUpRight className="size-4" />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="junta-btn-ghost junta-btn junta-btn-sm"
                                >
                                    Entrar
                                </Link>
                                <Link
                                    href={register()}
                                    className="junta-btn junta-btn-sm junta-btn-primary"
                                >
                                    Criar conta
                                </Link>
                            </>
                        )}
                    </div>
                    <button
                        onClick={() => setOpen(!open)}
                        aria-label={open ? 'Fechar menu' : 'Abrir menu'}
                        aria-expanded={open}
                        aria-controls="public-menu"
                        className="rounded-md p-2 lg:hidden"
                    >
                        {open ? (
                            <X className="size-5" />
                        ) : (
                            <Menu className="size-5" />
                        )}
                    </button>
                </div>
                {open && (
                    <nav
                        id="public-menu"
                        aria-label="Navegação móvel"
                        className="flex flex-col gap-1 border-t border-border p-4 lg:hidden"
                    >
                        {[
                            ...links,
                            ...(auth.user
                                ? [
                                      {
                                          label: 'Abrir aplicação',
                                          href: dashboard.url(),
                                      },
                                  ]
                                : [
                                      {
                                          label: 'Iniciar sessão',
                                          href: login.url(),
                                      },
                                      {
                                          label: 'Criar conta',
                                          href: register.url(),
                                      },
                                  ]),
                        ].map((l) => (
                            <Link
                                key={l.label}
                                href={l.href}
                                onClick={() => setOpen(false)}
                                className="rounded-md px-3 py-3 text-sm hover:bg-muted"
                            >
                                {l.label}
                            </Link>
                        ))}
                    </nav>
                )}
            </header>
            <DemoDataNotice />
            <div id="public-content" className="flex-1">
                {children}
            </div>
            <footer className="border-t border-primary bg-junta-primary-deep text-white">
                <div className="mx-auto grid w-full max-w-7xl gap-10 px-5 py-12 sm:grid-cols-2 sm:px-8 lg:grid-cols-[1.5fr_1fr_1fr_1.1fr] lg:gap-12 lg:px-10 lg:py-16">
                    <div>
                        <BrandLogo inverse href={home.url()} />
                        <p className="mt-5 max-w-xs text-sm leading-7 text-[#C5D9CE]">
                            Necessidades que se juntam. Procura que ganha força.
                            Uma forma mais organizada de comprar em conjunto.
                        </p>
                        <span className="mt-6 inline-flex rounded-full border border-white/15 px-3 py-2 text-xs text-[#C5D9CE]">
                            Compras colectivas · Moçambique
                        </span>
                    </div>
                    <nav aria-label="Explorar o Junta">
                        <h2 className="mb-5 text-sm font-semibold text-junta-accent">
                            Explore o Junta
                        </h2>
                        <ul className="space-y-4 text-sm text-[#C5D9CE]">
                            {links.map((link) => (
                                <li key={link.label}>
                                    <Link
                                        href={link.href}
                                        className="underline-offset-4 hover:text-white hover:underline"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                    <nav aria-label="Informação e acesso">
                        <h2 className="mb-5 text-sm font-semibold text-junta-accent">
                            Comece por aqui
                        </h2>
                        <ul className="space-y-4 text-sm text-[#C5D9CE]">
                            <li>
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                    className="underline-offset-4 hover:text-white hover:underline"
                                >
                                    {auth.user
                                        ? 'Abrir aplicação'
                                        : 'Criar conta de consumidor'}
                                </Link>
                            </li>
                            {!auth.user && (
                                <li>
                                    <Link
                                        href={login()}
                                        className="underline-offset-4 hover:text-white hover:underline"
                                    >
                                        Iniciar sessão
                                    </Link>
                                </li>
                            )}
                            <li>
                                <Link
                                    href={comoFunciona.url() + '#duvidas'}
                                    className="underline-offset-4 hover:text-white hover:underline"
                                >
                                    Perguntas frequentes
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href={fornecedores.url() + '#acesso'}
                                    className="underline-offset-4 hover:text-white hover:underline"
                                >
                                    Acesso para fornecedores
                                </Link>
                            </li>
                        </ul>
                    </nav>
                    <div className="rounded-2xl border border-white/15 bg-card/5 p-5">
                        <p className="text-sm font-semibold">
                            Antes de participar
                        </p>
                        <p className="mt-3 text-sm leading-6 text-[#C5D9CE]">
                            Conheça as condições de cada campanha. Participar
                            regista interesse e quantidade; não significa que já
                            pagou.
                        </p>
                        <Link
                            href={comoFunciona()}
                            className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-junta-accent"
                        >
                            Entender o processo
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </div>
                </div>
                <div className="border-t border-white/10">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-xs text-[#C5D9CE] sm:px-8 lg:px-10">
                        <p>
                            © {new Date().getFullYear()} Junta. Todos os
                            direitos reservados.
                        </p>
                        <p>Juntos compramos melhor.</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
