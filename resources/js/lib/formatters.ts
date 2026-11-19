/**
 * Formata moeda para Moçambique (Metical).
 */
export function formatMZN(value: number): string {
    return `${value.toLocaleString('pt-MZ')} MT`;
}

/**
 * Formata data para utilizador moçambicano (longa).
 */
export function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('pt-PT', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Formata data curta DD/MM/AAAA.
 */
export function formatDateShort(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('pt-PT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Calcula dias restantes até um prazo.
 */
export function daysRemaining(deadline: string): number {
    const diff = new Date(deadline).getTime() - Date.now();
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
}

/**
 * Calcula a percentagem de progresso (0–100).
 */
export function progressPercent(current: number, target: number): number {
    if (target === 0) return 0;
    return Math.min(100, Math.round((current / target) * 100));
}
