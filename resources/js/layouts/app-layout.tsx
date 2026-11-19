import WorkspaceLayout from '@/layouts/workspace-layout';
import { dashboard } from '@/routes';
import { explorar } from '@/routes/consumidor';
import { edit } from '@/routes/profile';
import { edit as security } from '@/routes/security';
import type { BreadcrumbItem } from '@/types';
import { House, Search, Shield, UserRound } from 'lucide-react';

export default function AppLayout({
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <WorkspaceLayout
            title="Aplicação Junta"
            demonstration={false}
            homeHref={dashboard.url()}
            items={[
                { title: 'Início', href: dashboard(), icon: House },
                { title: 'Explorar campanhas', href: explorar(), icon: Search },
                { title: 'Dados pessoais', href: edit(), icon: UserRound },
                { title: 'Segurança', href: security(), icon: Shield },
            ]}
        >
            {children}
        </WorkspaceLayout>
    );
}
