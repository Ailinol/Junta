import type { ReactNode } from 'react';
import { MapPin, Truck } from 'lucide-react';
import { CampaignManagementTabs } from '@/components/junta/CampaignManagementTabs';
import { StatusPill } from '@/components/junta/StatusBadge';
import {
    DeadlineLabel,
    NextStepBanner,
    PageShell,
    PhaseStepper,
    TestPaymentBanner,
} from '@/components/junta/OrganizerUi';
import { index } from '@/routes/organizador/campanhas';
import type { OrganizerCampaign } from '@/types/organizer';

export function CampaignManagementLayout({
    view,
    section,
    children,
    actions,
    testPayment = false,
}: {
    view: OrganizerCampaign;
    section: 'resumo' | 'participantes' | 'propostas' | 'compra';
    children: ReactNode;
    actions?: ReactNode;
    testPayment?: boolean;
}) {
    return (
        <PageShell
            title={view.campaign.title}
            description={view.campaign.reference}
            badge={<StatusPill state={view.state} />}
            back={{ href: index.url(), label: 'Campanhas' }}
            actions={view.state.isFinal ? undefined : actions}
        >
            <TestPaymentBanner visible={testPayment} />
            <div className="organizer-campaign-meta">
                <span>
                    <MapPin className="size-4" aria-hidden="true" />
                    {view.campaign.location}
                </span>
                {!view.state.isFinal && (
                    <DeadlineLabel deadline={view.deadline} />
                )}
                <span>
                    <Truck className="size-4" aria-hidden="true" />
                    {view.deliveryLabel}
                </span>
            </div>
            <PhaseStepper state={view.state} />
            <CampaignManagementTabs
                campaignId={view.campaign.id}
                active={section}
                participantCount={view.metrics.participants}
                proposalCount={view.metrics.proposals}
                attention={view.state.primaryAction?.target}
            />
            <NextStepBanner
                view={view}
                showAction={
                    view.state.primaryAction?.target !==
                    (section === 'compra'
                        ? 'purchase'
                        : section === 'participantes'
                          ? 'participants'
                          : section === 'propostas'
                            ? 'proposals'
                            : section)
                }
            />
            {children}
        </PageShell>
    );
}
