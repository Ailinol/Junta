import WorkspaceLayout from '@/layouts/workspace-layout';
import { dashboard, perfil } from '@/routes/fornecedor';
import { index as opportunities } from '@/routes/fornecedor/oportunidades';
import { index as proposals } from '@/routes/fornecedor/propostas';
import type { NavItem } from '@/types';
import { Briefcase, Building2, FileText, House } from 'lucide-react';
import type { ReactNode } from 'react';
const items: NavItem[] = [
    { title: 'Início', href: dashboard(), icon: House },
    { title: 'Oportunidades', href: opportunities(), icon: Briefcase },
    { title: 'Propostas', href: proposals(), icon: FileText },
    { title: 'Perfil comercial', href: perfil(), icon: Building2 },
];
export default function Layout({ children }: { children: ReactNode }) {
    return (
        <WorkspaceLayout
            title="Fornecedor"
            items={items}
            homeHref={dashboard.url()}
        >
            {children}
        </WorkspaceLayout>
    );
}
