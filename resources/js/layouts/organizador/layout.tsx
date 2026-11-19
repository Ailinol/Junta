import WorkspaceLayout from '@/layouts/workspace-layout';
import { dashboard } from '@/routes/organizador';
import { criar, index } from '@/routes/organizador/campanhas';
import type { NavItem } from '@/types';
import { House, Layers, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
const items: NavItem[] = [
    { title: 'Início', href: dashboard(), icon: House },
    { title: 'Campanhas', href: index(), icon: Layers },
    { title: 'Criar campanha', href: criar(), icon: Plus },
];
export default function Layout({ children }: { children: ReactNode }) {
    return (
        <WorkspaceLayout
            title="Organizador"
            items={items}
            homeHref={dashboard.url()}
        >
            {children}
        </WorkspaceLayout>
    );
}
