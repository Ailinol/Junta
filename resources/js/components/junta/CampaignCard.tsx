import { Link, usePage } from '@inertiajs/react';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { DeadlineLabel } from '@/components/junta/OrganizerUi';
import { campaignUnit } from '@/lib/campaign-presentation';
import { humanDeadline } from '@/lib/consumer-presentation';
import { money } from '@/components/junta/PurchaseForms';
import { show } from '@/routes/campanhas';
import { show as workspaceShow } from '@/routes/consumidor/campanhas';
import type { Campaign } from '@/types/junta';
export function CampaignCard({
    campaign,
    actionHref,
}: {
    campaign: Campaign;
    actionHref?: string;
}) {
    const { url } = usePage();
    const href =
        actionHref ??
        (url.startsWith('/consumidor/')
            ? workspaceShow.url(campaign.id)
            : show.url(campaign.id));
    const open =
        campaign.status === 'ativa' &&
        campaign.purchasePhase === 'interest' &&
        new Date(campaign.deadline).getTime() > Date.now();
    return (
        <article
            className="consumer-campaign-card"
            data-closed={!open || undefined}
        >
            <Link
                href={href}
                className="consumer-catalogue-photo"
                aria-hidden="true"
                tabIndex={-1}
            >
                <CampaignVisual
                    campaign={campaign}
                    categoryFallback
                    className="aspect-[4/3] rounded-none"
                />
                {!open && <span>Fechada</span>}
            </Link>
            <div className="consumer-campaign-card-body">
                <h2>
                    <Link href={href}>{campaign.title}</Link>
                </h2>
                <p className="consumer-muted">{campaign.location}</p>
                <p className="consumer-price-label">Preço estimado</p>
                <p className="consumer-card-price">
                    {campaign.estimatedPrice === undefined
                        ? 'Por confirmar'
                        : `≈ ${money(Math.round(campaign.estimatedPrice * 100))}`}{' '}
                    <span>/ {campaignUnit(campaign.unit)}</span>
                </p>
                <div className="consumer-demand">
                    <progress
                        value={Math.min(
                            campaign.currentQuantity,
                            campaign.targetQuantity,
                        )}
                        max={Math.max(1, campaign.targetQuantity)}
                        aria-label={`Procura: ${campaign.currentQuantity} de ${campaign.targetQuantity} ${campaign.unit}`}
                    />
                    <span>
                        {campaign.currentQuantity} de {campaign.targetQuantity}
                    </span>
                </div>
                <DeadlineLabel
                    deadline={humanDeadline(campaign.deadline)}
                    compact
                />
            </div>
        </article>
    );
}
