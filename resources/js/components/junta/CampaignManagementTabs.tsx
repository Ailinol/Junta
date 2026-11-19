import { Link } from '@inertiajs/react';
import { FileText, LayoutDashboard, PackageCheck, Users } from 'lucide-react';
import { participantes, propostas, show } from '@/routes/organizador/campanhas';
import { show as purchase } from '@/routes/organizador/compra';
import { cn } from '@/lib/utils';

type CampaignManagementSection =
    | 'resumo'
    | 'participantes'
    | 'propostas'
    | 'compra';

const sections = [
    { id: 'resumo', label: 'Resumo', icon: LayoutDashboard },
    { id: 'participantes', label: 'Participantes', icon: Users },
    { id: 'propostas', label: 'Propostas', icon: FileText },
    { id: 'compra', label: 'Pagamentos e entrega', icon: PackageCheck },
] as const;

export function CampaignManagementTabs({
    campaignId,
    active,
    participantCount = 0,
    proposalCount = 0,
    attention,
}: {
    campaignId: number;
    active: CampaignManagementSection;
    participantCount?: number;
    proposalCount?: number;
    attention?: string;
}) {
    return (
        <nav
            aria-label="Secções da gestão da campanha"
            className="organizer-tabs mt-6 border-b border-border pb-2"
        >
            <div className="grid grid-cols-2 gap-1 sm:flex sm:flex-wrap sm:gap-2">
                {sections.map((section) => {
                    const href =
                        section.id === 'resumo'
                            ? show(campaignId)
                            : section.id === 'compra'
                              ? purchase(campaignId)
                              : section.id === 'participantes'
                                ? participantes(campaignId)
                                : propostas(campaignId);
                    return (
                        <Link
                            key={section.id}
                            href={href}
                            aria-current={
                                active === section.id ? 'page' : undefined
                            }
                            className={cn(
                                'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:justify-start sm:px-4 sm:text-sm',
                                active === section.id
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            <section.icon
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {section.label}
                            {section.id === 'participantes' &&
                                participantCount > 0 && (
                                    <span className="organizer-tab-count">
                                        {participantCount}
                                    </span>
                                )}
                            {section.id === 'propostas' &&
                                proposalCount > 0 && (
                                    <span className="organizer-tab-count">
                                        {proposalCount}
                                    </span>
                                )}
                            {section.id === 'compra' &&
                                attention === 'purchase' && (
                                    <span
                                        aria-label="Requer atenção"
                                        className="organizer-attention-dot size-1.5 rounded-full"
                                    />
                                )}
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
