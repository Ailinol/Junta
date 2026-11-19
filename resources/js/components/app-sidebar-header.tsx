import { Link, usePage } from '@inertiajs/react';
import { Bell, UserRound } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import AppearanceToggleTab from '@/components/appearance-tabs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { edit as profileEdit } from '@/routes/profile';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function titleForUrl(url: string): string {
    const path = url.split(/[?#]/, 1)[0];
    const titles: Record<string, string> = {
        '/consumidor/dashboard': 'Painel do consumidor',
        '/consumidor/campanhas': 'Minhas compras',
        '/consumidor/notificacoes': 'Notificações',
        '/organizador/dashboard': 'Painel do organizador',
        '/organizador/campanhas': 'Minhas campanhas',
        '/organizador/campanhas/criar': 'Criar campanha',
        '/fornecedor/dashboard': 'Painel do fornecedor',
        '/fornecedor/oportunidades': 'Oportunidades',
        '/fornecedor/propostas': 'Minhas propostas',
        '/fornecedor/perfil': 'Perfil comercial',
        '/admin/dashboard': 'Painel administrativo',
        '/admin/utilizadores': 'Utilizadores',
        '/admin/campanhas': 'Campanhas',
        '/admin/fornecedores': 'Fornecedores',
        '/admin/propostas': 'Propostas',
        '/admin/relatorios': 'Relatórios',
        '/settings/profile': 'Perfil e definições',
        '/settings/security': 'Segurança',
        '/settings/appearance': 'Aparência',
    };

    return (
        titles[path] ??
        (path.includes('/participantes')
            ? 'Participantes da campanha'
            : path.includes('/propostas')
              ? 'Propostas da campanha'
              : path.includes('/acompanhamento')
                ? 'Acompanhamento da compra'
                : path.includes('/campanhas/')
                  ? 'Detalhes da campanha'
                  : path.includes('/oportunidades/')
                    ? 'Detalhes da oportunidade'
                    : 'Junta')
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
    notificationsHref,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    notificationsHref?: string;
}) {
    const page = usePage();
    const currentTitle = breadcrumbs.at(-1)?.title ?? titleForUrl(page.url);

    return (
        <header className="sticky top-0 z-30 flex min-h-14 shrink-0 items-center justify-between gap-3 border-b border-sidebar-border bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                {breadcrumbs.length > 0 ? (
                    <>
                        <span className="truncate text-sm font-semibold sm:hidden">
                            {currentTitle}
                        </span>
                        <div className="hidden min-w-0 sm:block">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    </>
                ) : (
                    <h1 className="truncate text-sm font-semibold text-foreground">
                        {currentTitle}
                    </h1>
                )}
            </div>
            <nav
                aria-label="Acesso rápido"
                className="flex shrink-0 items-center gap-1"
            >
                <AppearanceToggleTab compact />
                {notificationsHref && (
                    <Link
                        href={notificationsHref}
                        prefetch
                        aria-label="Notificações"
                        title="Notificações"
                        className="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <Bell className="size-4" aria-hidden="true" />
                    </Link>
                )}
                {page.props.auth?.user && (
                    <Link
                        href={profileEdit()}
                        prefetch
                        className="inline-flex h-9 items-center gap-2 rounded-md px-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <UserRound className="size-4" aria-hidden="true" />
                        <span className="hidden sm:inline">Perfil</span>
                    </Link>
                )}
            </nav>
        </header>
    );
}
