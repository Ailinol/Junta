import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ChartNoAxesCombined,
    ClipboardCheck,
    History,
    House,
    Layers,
    ReceiptText,
    UserRoundCheck,
    Users,
} from 'lucide-react';
import { dashboard, historico, relatorios } from '@/routes/admin';
import { index as purchases } from '@/routes/admin/compras';
import { index as tariffs } from '@/routes/admin/tarifas';
import { index as applications } from '@/routes/admin/candidaturas';
import { index as suppliers } from '@/routes/admin/fornecedores';
import { index as users } from '@/routes/admin/utilizadores';
import { index as campaigns } from '@/routes/admin/campanhas';
import { index as proposals } from '@/routes/admin/propostas';
export function AdminSidebarGroups({
    onNavigate,
}: {
    onNavigate?: () => void;
}) {
    const { url, props } = usePage<{
        adminNavigation?: {
            interventions: number;
            applications: number;
            suppliers: number;
        } | null;
    }>();
    const counts = props.adminNavigation;
    const groups = [
        {
            label: '',
            items: [
                {
                    label: 'Início',
                    href: dashboard.url(),
                    icon: House,
                    count: 0,
                },
                {
                    label: 'Compras e financeiro',
                    href: purchases.url(),
                    icon: ReceiptText,
                    count: counts?.interventions ?? 0,
                },
                {
                    label: 'Tarifas da plataforma',
                    href: tariffs.url(),
                    icon: ReceiptText,
                    count: 0,
                },
            ],
        },
        {
            label: 'Pessoas e acessos',
            items: [
                {
                    label: 'Candidaturas',
                    href: applications.url(),
                    icon: UserRoundCheck,
                    count: counts?.applications ?? 0,
                },
                {
                    label: 'Fornecedores',
                    href: suppliers.url(),
                    icon: Building2,
                    count: counts?.suppliers ?? 0,
                },
                {
                    label: 'Utilizadores',
                    href: users.url(),
                    icon: Users,
                    count: 0,
                },
            ],
        },
        {
            label: 'Plataforma',
            items: [
                {
                    label: 'Campanhas',
                    href: campaigns.url(),
                    icon: Layers,
                    count: 0,
                },
                {
                    label: 'Propostas',
                    href: proposals.url(),
                    icon: ClipboardCheck,
                    count: 0,
                },
            ],
        },
        {
            label: 'Análise',
            items: [
                {
                    label: 'Relatórios',
                    href: relatorios.url(),
                    icon: ChartNoAxesCombined,
                    count: 0,
                },
                {
                    label: 'Histórico',
                    href: historico.url(),
                    icon: History,
                    count: 0,
                },
            ],
        },
    ];
    return (
        <nav className="admin-sidebar-groups" aria-label="Administração">
            {groups.map((group) => (
                <section
                    key={group.label}
                    role="group"
                    aria-label={group.label || 'Operação'}
                >
                    {group.label && <h2>{group.label}</h2>}
                    {group.items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            onClick={onNavigate}
                            aria-current={
                                url.split('?')[0] === item.href
                                    ? 'page'
                                    : undefined
                            }
                        >
                            <item.icon
                                className="size-[18px]"
                                aria-hidden="true"
                            />
                            <span>{item.label}</span>
                            {item.count > 0 && (
                                <small
                                    className="admin-nav-count"
                                    aria-label={`${item.count} pendentes`}
                                >
                                    {item.count}
                                </small>
                            )}
                        </Link>
                    ))}
                </section>
            ))}
        </nav>
    );
}
