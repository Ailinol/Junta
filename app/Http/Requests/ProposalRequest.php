<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use App\Support\CommercialDate;
use App\Support\Money;
use App\Support\PlatformRules;
use App\Support\ProposalValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProposalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['validUntil', 'reservedUntil'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => CommercialDate::normalize($this->input($field))]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can($this->isAdministrativeProposalRequest() ? 'administer-platform' : 'supply-campaigns') ?? false;
    }

    private function isAdministrativeProposalRequest(): bool
    {
        return $this->routeIs('admin.propostas.*');
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        $admin = $this->isAdministrativeProposalRequest();
        $commercial = $admin ? 'nullable' : 'required';

        return [
            'campaignId' => ['required', 'integer', 'exists:campaigns,id'],
            'supplierId' => [Rule::excludeIf(! $admin), 'required', 'integer', 'exists:suppliers,id'],
            ...ProposalValidation::rules(),
            'availableQuantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'minimumQuantity' => [$commercial, 'integer', 'min:1', 'lte:availableQuantity'],
            'reservedUntil' => [$commercial, 'date', 'after:now'],
            'transportIncluded' => [$commercial, 'boolean'],
            'transportUnitPrice' => [Rule::requiredIf(! $admin && ! $this->boolean('transportIncluded')), 'nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'totalEstimated' => ['nullable', 'numeric', 'gt:0', 'max:99999999999999.99', 'decimal:0,2'],
            'deliveryDays' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'deliveryMode' => ['required', Rule::in(PlatformRules::DELIVERY_MODES)],
            'validUntil' => array_filter(['required', 'date', ! $admin || $this->isMethod('post') ? 'after:now' : null]),
            'status' => [Rule::excludeIf(! $admin), 'required', Rule::in(PlatformRules::PROPOSAL_STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [...ProposalValidation::messages(), 'required' => 'Preencha este campo.', 'minimumQuantity.lte' => 'O mínimo comercial não pode ultrapassar a quantidade disponível.', 'reservedUntil.after' => 'Indique uma reserva futura.', 'validUntil.after' => 'Indique uma validade futura.', 'status.in' => 'A aceitação de uma proposta só pode ser feita na comparação de propostas.'];
    }

    /** @return array<string,mixed> */
    public function proposalData(): array
    {
        $data = $this->validated();
        $transport = isset($data['transportUnitPrice']) ? Money::cents((string) $data['transportUnitPrice']) : null;
        if (($data['transportIncluded'] ?? false) && $transport !== null && $transport !== 0) {
            throw ValidationException::withMessages(['transportUnitPrice' => 'Transporte incluído exige custo adicional zero.']);
        }
        if ($data['transportIncluded'] ?? false) {
            $transport = 0;
        }
        $supplier = $this->isAdministrativeProposalRequest() ? Supplier::query()->lockForUpdate()->whereKey($this->integer('supplierId'))->firstOrFail() : $this->user()->supplier()->lockForUpdate()->firstOrFail();
        if (! $supplier->verified) {
            throw ValidationException::withMessages(['supplierId' => 'O fornecedor precisa de verificação administrativa para apresentar propostas.']);
        }

        $total = $data['totalEstimated'] ?? round($data['unitPrice'] * $data['availableQuantity'], 2);
        if (! is_finite((float) $total) || $total > 99999999999999.99) {
            throw ValidationException::withMessages(['totalEstimated' => 'O total excede o intervalo numérico suportado.']);
        }

        return ['minimum_quantity' => $data['minimumQuantity'] ?? null, 'reserved_until' => $data['reservedUntil'] ?? null, 'transport_included' => $data['transportIncluded'] ?? null, 'transport_unit_cents' => $transport, 'campaign_id' => $data['campaignId'], 'supplier_id' => $supplier->id, 'unit_price' => $data['unitPrice'], 'available_quantity' => $data['availableQuantity'], 'total_estimated' => $total, 'delivery_days' => $data['deliveryDays'], 'delivery_mode' => $data['deliveryMode'], 'valid_until' => $data['validUntil'], 'status' => $data['status'] ?? 'submetida', 'notes' => $data['notes'] ?? null];
    }
}
