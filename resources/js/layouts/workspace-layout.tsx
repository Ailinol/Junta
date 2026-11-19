import { DevRoleSwitch } from '@/components/junta/DevRoleSwitch';
import AppearanceMenu from '@/components/appearance-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { BrandLogo } from '@/components/junta/BrandLogo';
import { DemoDataNotice } from '@/components/junta/DemoDataNotice';
import { NavUser } from '@/components/nav-user';
import { SidebarProvider } from '@/components/ui/sidebar';
import { cn, toUrl } from '@/lib/utils';
import { home } from '@/routes';
import { index as notificacoes } from '@/routes/notifications';
import { edit as profile } from '@/routes/profile';
import type { NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Bell, ChevronRight, Menu, Settings } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type WorkspaceLayoutProps = {
    children: ReactNode;
    title: string;
    items: NavItem[];
    homeHref: string;
    demonstration?: boolean;
};

export default function WorkspaceLayout({
    children,
    title,
    items,
    homeHref,
    demonstration = true,
}: WorkspaceLayoutProps) {
    const { url, props, component } = usePage();
    const isAdministration = component.startsWith('admin/');
    const isOrganizer = component.startsWith('organizador/');
    const [open, setOpen] = useState(false);
    const path = url.split('?')[0];
    const current = items
        .filter(
            (item) =>
                path === toUrl(item.href) ||
                path.startsWith(`${toUrl(item.href)}/`),
        )
        .sort((a, b) => toUrl(b.href).length - toUrl(a.href).length)[0];
    const navigationSections = [
        { label: 'Operação', items: items.slice(0, 4) },
        { label: 'Pessoas e acessos', items: items.slice(4, 7) },
        { label: 'Análise', items: items.slice(7) },
    ];
    const navigationLink = (item: NavItem) => (
        <Link
            key={item.title}
            href={item.href}
            onClick={() => setOpen(false)}
            aria-current={current === item ? 'page' : undefined}
            className={cn(
                'flex min-h-11 items-center gap-3 rounded-xl px-3 text-[13px] font-medium transition-colors',
                current === item
                    ? 'bg-junta-primary-soft text-primary ring-1 ring-primary/10'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
        >
            {item.icon && (
                <item.icon
                    className="size-[18px] shrink-0"
                    aria-hidden="true"
                />
            )}
            {item.title}
        </Link>
    );
    const navigation = (
        <>
            <div className="junta-workspace-brand flex h-24 items-center justify-between px-7">
                <BrandLogo compact href={homeHref} />
            </div>
            <p className="junta-workspace-label px-7 pb-4 text-[10px] font-semibold tracking-[.16em] text-muted-foreground uppercase">
                {title}
            </p>
            <nav
                aria-label={`Área de ${title}`}
                className="flex-1 space-y-1.5 overflow-y-auto px-4"
            >
                {isAdministration
                    ? navigationSections.map((section) => (
                          <div
                              key={section.label}
                              className="junta-admin-nav-section"
                              role="group"
                              aria-label={section.label}
                          >
                              <p aria-hidden="true">{section.label}</p>
                              {section.items.map(navigationLink)}
                          </div>
                      ))
                    : items.map(navigationLink)}
            </nav>
            <div className="junta-workspace-footer space-y-1 border-t border-border p-4">
                <Link
                    href={home()}
                    className="flex items-center justify-between rounded-xl px-3 py-2.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Visitar site público
                    <ArrowUpRight className="size-4" />
                </Link>
                <Link
                    href={profile()}
                    onClick={() => setOpen(false)}
                    className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <Settings className="size-[18px]" />
                    Definições da conta
                </Link>
                {props.auth?.user && (
                    <div className="mt-3 rounded-xl border border-border bg-background text-foreground">
                        <NavUser />
                    </div>
                )}
            </div>
        </>
    );
    return (
        <SidebarProvider>
            <Sheet open={open} onOpenChange={setOpen}>
                <a
                    href="#workspace-content"
                    className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-md focus:bg-card focus:p-3"
                >
                    Saltar para o conteúdo
                </a>
                <div
                    className={cn(
                        'workspace flex min-h-svh w-full',
                        isAdministration && 'junta-admin-workspace',
                        isOrganizer && 'junta-organizer-workspace',
                    )}
                >
                    <aside className="workspace-nav fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-border bg-card text-foreground lg:flex">
                        {navigation}
                    </aside>
                    <SheetContent
                        side="left"
                        className={cn(
                            'flex w-64 flex-col gap-0 bg-card p-0 text-foreground lg:hidden',
                            isAdministration && 'junta-admin-drawer',
                            isOrganizer && 'junta-organizer-drawer',
                        )}
                        aria-describedby={undefined}
                    >
                        <SheetTitle className="sr-only">
                            Navegação de {title}
                        </SheetTitle>
                        {navigation}
                    </SheetContent>
                    <div className="junta-workspace-body flex min-w-0 flex-1 flex-col lg:pl-64">
                        <header className="junta-workspace-topbar flex h-[72px] shrink-0 items-center justify-between gap-2 border-b border-border bg-card/90 px-4 sm:px-8">
                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                <SheetTrigger asChild>
                                    <button
                                        className="rounded-md p-2 lg:hidden"
                                        aria-label="Abrir navegação"
                                    >
                                        <Menu className="size-5" />
                                    </button>
                                </SheetTrigger>
                                {isAdministration ? (
                                    <>
                                        <Link
                                            href={homeHref}
                                            className="hidden text-sm text-muted-foreground hover:text-foreground sm:inline"
                                        >
                                            {title}
                                        </Link>
                                        <ChevronRight
                                            className="hidden size-3.5 shrink-0 text-muted-foreground sm:inline"
                                            aria-hidden="true"
                                        />
                                    </>
                                ) : (
                                    <>
                                        <span className="hidden shrink-0 text-sm text-muted-foreground sm:inline">
                                            {title}
                                        </span>
                                        <span className="hidden text-border sm:inline">
                                            /
                                        </span>
                                    </>
                                )}
                                <span className="junta-workspace-current truncate text-sm font-medium">
                                    {current?.title ?? 'Detalhes'}
                                </span>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <AppearanceMenu />
                                <DevRoleSwitch />
                            </div>
                            {props.auth?.user && (
                                <Link
                                    href={notificacoes()}
                                    aria-label="Notificações"
                                    className="rounded-md p-2 text-muted-foreground hover:bg-muted"
                                >
                                    <Bell className="size-5" />
                                </Link>
                            )}
                        </header>
                        {demonstration && <DemoDataNotice />}
                        <div
                            id="workspace-content"
                            tabIndex={-1}
                            className="min-w-0 flex-1 outline-none"
                        >
                            {children}
                        </div>
                    </div>
                </div>
            </Sheet>
        </SidebarProvider>
    );
}
