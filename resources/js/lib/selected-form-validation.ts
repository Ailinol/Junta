export const quantityMessage =
    'A quantidade deve ser um número inteiro maior que zero.';
export const priceMessage = 'O preço deve ser um número maior que zero.';

export function quantityError(value: string): string {
    const raw = value.trim();
    if (!/^\+?[1-9]\d*$/.test(raw)) return quantityMessage;
    return Number.isSafeInteger(Number(raw)) && Number(raw) <= 2147483647
        ? ''
        : quantityMessage;
}
export function priceError(value: string): string {
    const raw = value.trim();
    if (!raw || !Number.isFinite(Number(raw)) || Number(raw) <= 0)
        return priceMessage;
    if (!/^\+?(?:\d+(?:\.\d{0,2})?|\.\d{1,2})$/.test(raw))
        return 'Use no máximo duas casas decimais no preço.';
    return Number(raw) <= 999999999999.99
        ? ''
        : 'O preço excede o limite suportado.';
}
type ProposalInput = {
    unitPrice: string;
    availableQuantity: string;
    deliveryDays: string;
    validUntil: string;
    minimumQuantity: string;
    reservedUntil: string;
    transportIncluded: string;
    transportUnitPrice: string;
};
export function proposalReviewErrors(
    form: ProposalInput,
): Record<string, string> {
    const errors: Record<string, string> = {};
    const price = priceError(form.unitPrice);
    if (price) errors.unitPrice = price;
    if (quantityError(form.availableQuantity))
        errors.availableQuantity =
            'Indique uma quantidade inteira maior que zero.';
    if (quantityError(form.deliveryDays))
        errors.deliveryDays = 'Indique um prazo inteiro maior que zero.';
    if (!(new Date(form.validUntil).getTime() > Date.now()))
        errors.validUntil = 'Indique uma validade futura.';
    if (quantityError(form.minimumQuantity))
        errors.minimumQuantity =
            'Indique um mínimo comercial inteiro maior que zero.';
    else if (Number(form.minimumQuantity) > Number(form.availableQuantity))
        errors.minimumQuantity =
            'O mínimo comercial não pode ultrapassar a quantidade disponível.';
    if (!(new Date(form.reservedUntil).getTime() > Date.now()))
        errors.reservedUntil = 'Indique uma reserva futura.';
    const transport = Number(form.transportUnitPrice);
    if (
        !form.transportUnitPrice.trim() ||
        !/^\d+(?:\.\d{1,2})?$/.test(form.transportUnitPrice) ||
        transport < 0 ||
        transport > 9999999999.99 ||
        (form.transportIncluded === '1' && transport !== 0)
    )
        errors.transportUnitPrice =
            form.transportIncluded === '1'
                ? 'Transporte incluído exige custo adicional zero.'
                : 'Indique um custo de transporte válido, com até duas casas decimais.';
    return errors;
}
