import { Link, usePage } from '@inertiajs/react';
import { Bell, Menu } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { BrandLogo } from '@/components/junta/BrandLogo';
import { AdminSidebarGroups } from '@/components/junta/AdminSidebarGroups';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard } from '@/routes/admin';
import { index as notifications } from '@/routes/notifications';
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    return (
        <div className="junta-admin-console">
            <a href="#admin-content" className="consumer-skip">
                Saltar para o conteúdo
            </a>
            <aside className="admin-sidebar">
                <BrandLogo compact href={dashboard.url()} />
                <AdminSidebarGroups />
            </aside>
            <div className="admin-body">
                <header className="admin-topbar">
                    <div>
                        <Sheet open={open} onOpenChange={setOpen}>
                            <SheetTrigger asChild>
                                <button
                                    className="admin-mobile-menu"
                                    type="button"
                                    aria-label="Abrir navegação"
                                >
                                    <Menu className="size-5" />
                                </button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="admin-navigation-drawer"
                            >
                                <SheetTitle className="sr-only">
                                    Administração
                                </SheetTitle>
                                <BrandLogo compact href={dashboard.url()} />
                                <AdminSidebarGroups
                                    onNavigate={() => setOpen(false)}
                                />
                            </SheetContent>
                        </Sheet>
                        <span>Administração</span>
                    </div>
                    <div>
                        <Link
                            href={notifications({ query: { area: 'admin' } })}
                            className="admin-icon-button"
                            aria-label="Notificações"
                        >
                            <Bell className="size-5" />
                        </Link>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="consumer-account"
                                    aria-label="Menu da conta"
                                >
                                    <UserInfo user={auth.user} />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                                <UserMenuContent
                                    user={auth.user}
                                    administrator
                                />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>
                <div id="admin-content" tabIndex={-1}>
                    {children}
                </div>
            </div>
        </div>
    );
}
