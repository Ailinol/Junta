<?php

namespace App\Support;

use App\Models\Proposal;
use Carbon\CarbonImmutable;

class ProposalValidation
{
    /**
     * @return array{proposal_id:int,supplier_id:int,unit_cents:int,minimum_quantity:int|null,available_quantity:int,transport_included:bool|null,transport_unit_cents:int|null,transport_distribution:string,delivery_days:int,price_valid_until:string,reserved_until:string|null}
     */
    public static function conditions(Proposal $proposal): array
    {
        $conditions = [
            'proposal_id' => $proposal->id, 'supplier_id' => $proposal->supplier_id,
            'unit_cents' => Money::cents($proposal->unit_price),
            'minimum_quantity' => $proposal->minimum_quantity, 'available_quantity' => $proposal->available_quantity,
            'transport_included' => $proposal->transport_included,
            'transport_unit_cents' => $proposal->transport_included ? 0 : $proposal->transport_unit_cents,
            'transport_distribution' => 'fixed_per_unit', 'delivery_days' => $proposal->delivery_days,
            'price_valid_until' => $proposal->valid_until->toIso8601String(), 'reserved_until' => $proposal->reserved_until?->toIso8601String(),
        ];

        return $conditions;
    }

    /** @param array<string,mixed> $conditions */
    public static function conditionsBlocker(array $conditions): ?string
    {
        if (($conditions['unit_cents'] ?? 0) <= 0 || ($conditions['minimum_quantity'] ?? 0) <= 0 || ($conditions['available_quantity'] ?? 0) < $conditions['minimum_quantity']
            || ($conditions['delivery_days'] ?? 0) < 1 || ! isset($conditions['transport_included'], $conditions['transport_unit_cents'])
            || $conditions['transport_unit_cents'] < 0 || ($conditions['transport_included'] && $conditions['transport_unit_cents'] !== 0)
            || empty($conditions['price_valid_until']) || empty($conditions['reserved_until'])) {
            return 'Esta proposta antiga não tem todas as condições comerciais necessárias. O organizador deve resolver a situação antes de pedir pagamentos.';
        }
        if (! CarbonImmutable::parse($conditions['price_valid_until'])->isFuture() || ! CarbonImmutable::parse($conditions['reserved_until'])->isFuture()) {
            return 'As condições da proposta expiraram. O organizador deve decidir como prosseguir antes de pedir pagamentos.';
        }

        return null;
    }

    /** @return array{unitPrice:list<string>} */
    public static function rules(): array
    {
        return ['unitPrice' => ['bail', 'required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2']];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return ['unitPrice.required' => 'O preço deve ser um número maior que zero.', 'unitPrice.numeric' => 'O preço deve ser um número maior que zero.', 'unitPrice.gt' => 'O preço deve ser um número maior que zero.'];
    }
}
