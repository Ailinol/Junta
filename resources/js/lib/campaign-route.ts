export function campaignIdFromUrl(url: string, fallback = 1): number {
    const [path, query = ''] = url.split('?');
    const queryId = Number(new URLSearchParams(query).get('campaignId'));
    if (Number.isInteger(queryId) && queryId > 0) {
        return queryId;
    }

    const match = path.match(
        /\/(?:campanhas|oportunidades|acompanhamento)\/(\d+)(?:\/|$)/,
    );
    const pathId = Number(match?.[1]);

    return Number.isInteger(pathId) && pathId > 0 ? pathId : fallback;
}
