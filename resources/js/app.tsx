import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import NotificationLayout from '@/layouts/notification-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ConsumidorLayout from '@/layouts/consumidor/layout';
import OrganizadorLayout from '@/layouts/organizador/layout';
import FornecedorLayout from '@/layouts/fornecedor/layout';
import AdminLayout from '@/layouts/admin/layout';
import PublicoLayout from '@/layouts/publico/layout';

const appName = 'Junta';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'notificacoes':
                return NotificationLayout;
            case name === 'welcome':
                return PublicoLayout;
            case name.startsWith('campanhas/'):
                return PublicoLayout;
            case name.startsWith('publico/'):
                return PublicoLayout;
            case name.startsWith('consumidor/'):
                return ConsumidorLayout;
            case name.startsWith('organizador/'):
                return OrganizadorLayout;
            case name.startsWith('fornecedor/'):
                return FornecedorLayout;
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#155B43',
    },
});

// This will set light / dark mode on load...
initializeTheme();
