export function progressValue(value: number, target: number) {
    return {
        value: Math.max(0, Math.min(value, Math.max(1, target))),
        max: Math.max(1, target),
        percent:
            target > 0
                ? Math.min(100, Math.max(0, Math.round((value / target) * 100)))
                : 0,
    };
}
export function maputoInput(value: string): string {
    if (!value || !Number.isFinite(Date.parse(value))) return '';
    return new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Africa/Maputo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    })
        .format(new Date(value))
        .replace(' ', 'T');
}
export function maputoDate(value: string): string {
    return new Date(value).toLocaleString('pt-PT', {
        timeZone: 'Africa/Maputo',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
export function paymentExample(
    unitCents: number,
    transportCents: number,
    commission: string,
    delivery: string,
) {
    const bps = Math.round(Number(commission) * 100);
    const deliveryCents = Math.round(Number(delivery) * 100);
    if (
        ![unitCents, transportCents, bps, deliveryCents].every(
            Number.isSafeInteger,
        ) ||
        bps < 0 ||
        bps > 10000 ||
        deliveryCents < 0 ||
        unitCents < 0 ||
        transportCents < 0
    )
        return null;
    const commissionCents = Number(
        (BigInt(unitCents) * BigInt(bps) + 5000n) / 10000n,
    );
    return {
        commissionCents,
        pickupCents: unitCents + transportCents + commissionCents,
        deliveryCents:
            unitCents + transportCents + commissionCents + deliveryCents,
    };
}
export function csvValue(value: string | number | null | undefined): string {
    return (
        '"' +
        String(value ?? '')
            .replace(/^\s*[=+\-@]/, "'$&")
            .replaceAll('"', '""') +
        '"'
    );
}

export function relativeAge(value: string, now = Date.now()): string {
    const seconds = Math.max(0, Math.floor((now - Date.parse(value)) / 1000));
    if (seconds < 60) return 'agora';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `há ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `há ${hours} h`;
    const days = Math.floor(hours / 24);
    return days === 1 ? 'há 1 dia' : `há ${days} dias`;
}
