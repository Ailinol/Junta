import { Link, usePage } from '@inertiajs/react';
import { Bell, House, Search, ShoppingBag } from 'lucide-react';
import type { ReactNode } from 'react';
import AppearanceMenu from '@/components/appearance-menu';
import { BrandLogo } from '@/components/junta/BrandLogo';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard, explorar, notificacoes } from '@/routes/consumidor';
import { index as purchases } from '@/routes/consumidor/campanhas';
const items = [
    { title: 'Início', href: dashboard(), icon: House, key: 'home' },
    { title: 'Explorar', href: explorar(), icon: Search, key: 'explore' },
    {
        title: 'As minhas compras',
        href: purchases(),
        icon: ShoppingBag,
        key: 'orders',
    },
    {
        title: 'Notificações',
        href: notificacoes(),
        icon: Bell,
        key: 'notifications',
    },
];
export function BottomNav({ active }: { active: string }) {
    return (
        <nav className="consumer-bottom-nav" aria-label="Navegação principal">
            {items.map((item) => (
                <Link
                    key={item.key}
                    href={item.href}
                    aria-current={active === item.key ? 'page' : undefined}
                >
                    <item.icon className="size-5" aria-hidden="true" />
                    <span>{item.title}</span>
                </Link>
            ))}
        </nav>
    );
}
export default function Layout({ children }: { children: ReactNode }) {
    const { component, props } = usePage();
    const minimal = component === 'consumidor/checkout';
    const active = component.endsWith('dashboard')
        ? 'home'
        : component.includes('explorar') ||
            component.endsWith('campanhas/detalhes')
          ? 'explore'
          : component.endsWith('notificacoes')
            ? 'notifications'
            : 'orders';
    return (
        <div
            className="junta-consumer-workspace"
            data-minimal={minimal || undefined}
        >
            <a href="#consumer-content" className="consumer-skip">
                Saltar para o conteúdo
            </a>
            {!minimal && (
                <aside className="consumer-sidebar">
                    <BrandLogo compact href={dashboard.url()} />
                    <nav aria-label="Área do consumidor">
                        {items.map((item) => (
                            <Link
                                key={item.key}
                                href={item.href}
                                aria-current={
                                    active === item.key ? 'page' : undefined
                                }
                            >
                                <item.icon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                                {item.title}
                            </Link>
                        ))}
                    </nav>
                </aside>
            )}
            <div className="consumer-workspace-body">
                <header className="consumer-topbar">
                    <Link
                        href={minimal ? purchases() : dashboard()}
                        className="consumer-topbar-title"
                    >
                        {minimal ? 'As minhas compras' : 'Junta'}
                    </Link>
                    <div>
                        <AppearanceMenu />
                        {props.auth.user && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <button
                                        className="consumer-account"
                                        aria-label="Menu da conta"
                                    >
                                        <UserInfo user={props.auth.user} />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-64"
                                >
                                    <UserMenuContent
                                        user={props.auth.user}
                                        consumer
                                    />
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </header>
                <div id="consumer-content" tabIndex={-1}>
                    {children}
                </div>
            </div>
            {!minimal && <BottomNav active={active} />}
        </div>
    );
}
