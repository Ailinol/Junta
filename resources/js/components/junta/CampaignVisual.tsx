import { Package } from 'lucide-react';
import { useState } from 'react';
import { campaignCategories, campaignTitle } from '@/lib/campaign-presentation';
import { cn } from '@/lib/utils';
import type { Campaign } from '@/types/junta';

/** Only an explicitly supplied product photograph is shown. */
export function CampaignVisual({
    campaign,
    thumbnail = false,
    hideFallback = false,
    categoryFallback = false,
    className,
}: {
    campaign: Campaign;
    thumbnail?: boolean;
    hideFallback?: boolean;
    categoryFallback?: boolean;
    className?: string;
}) {
    const source = campaign.imageUrl;
    const [failedSource, setFailedSource] = useState<string>();
    const hasImage = source && failedSource !== source;
    if (hideFallback && !hasImage) return null;
    return (
        <div
            className={cn(
                'relative overflow-hidden bg-muted',
                thumbnail
                    ? 'size-16 shrink-0 rounded-xl border border-primary/10'
                    : 'aspect-[16/9] w-full rounded-2xl',
                className,
            )}
        >
            {hasImage ? (
                <img
                    src={source}
                    alt={thumbnail ? '' : campaignTitle(campaign.title)}
                    loading="lazy"
                    decoding="async"
                    onError={() => setFailedSource(source)}
                    className="size-full object-cover"
                />
            ) : categoryFallback ? (
                <div
                    className="consumer-category-initial"
                    aria-label={campaignCategories[campaign.category]}
                >
                    {campaignCategories[campaign.category].slice(0, 1)}
                </div>
            ) : (
                <div className="flex size-full flex-col items-center justify-center gap-3 text-muted-foreground">
                    <Package
                        className={thumbnail ? 'size-7' : 'size-12'}
                        aria-hidden="true"
                    />
                    {!thumbnail && (
                        <span className="text-sm font-medium">
                            {campaignCategories[campaign.category]}
                            <span className="mt-1 block text-center text-xs font-normal">
                                Sem fotografia do produto
                            </span>
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
