<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use App\Support\CommercialDate;
use App\Support\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['paymentDeadline'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => CommercialDate::normalize($this->input($field))]);
            }
        }
    }

    public function authorize(): bool
    {
        if ($this->route()->getActionMethod() === 'pay') {
            $order = $this->route('order');
            abort_unless($order instanceof PurchaseOrder && $order->user_id === $this->user()?->id, 404);
        }

        return $this->user() !== null;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        $money = ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'];
        $reason = ['required', 'string', 'max:2000'];
        $reference = ['required', 'string', 'max:120'];
        $positive = ['required', 'integer', 'min:1', 'max:2147483647'];

        if ($this->route()->getActionMethod() === 'pay' || ($this->route()->getActionMethod() === 'store' && $this->has('paymentMethod'))) {
            if (! app(PaymentGateway::class)->available()) {
                return [];
            }
            $methods = app(PaymentGateway::class)->availableMethods();
            $method = collect($methods)->firstWhere('code', $this->input('paymentMethod'));

            return [
                'paymentMethod' => ['required', Rule::in(array_column($methods, 'code'))],
                'paymentPhone' => [Rule::excludeIf(! (bool) ($method['requiresPhone'] ?? false)), Rule::requiredIf((bool) ($method['requiresPhone'] ?? false)), 'nullable', 'string', 'regex:/^\+258[0-9]{9}$/D'],
                'deliveryMethod' => ['required', Rule::in(['recolha', 'entrega'])],
                'deliveryAddress' => ['required_if:deliveryMethod,entrega', 'nullable', 'string', 'max:1000'],
                'termsVersion' => ['required', 'string', 'max:120'], 'acceptTerms' => ['required', 'accepted'],
            ];
        }

        return match ($this->route()->getActionMethod()) {
            'open' => [
                'commissionPercent' => ['exclude'],
                'paymentDeadline' => ['required', 'date', 'after:now'],
                'pickupPoint' => ['required', 'string', 'max:500'],
                'deliveryZone' => ['exclude'], 'deliveryFee' => ['exclude'],
            ],
            'store' => [
                'deliveryMethod' => ['required', Rule::in(['recolha', 'entrega'])],
                'deliveryAddress' => ['required_if:deliveryMethod,entrega', 'nullable', 'string', 'max:1000'],
                'termsVersion' => ['required', 'string', 'max:120'], 'acceptTerms' => ['required', 'accepted'],
            ],
            'receive' => ['receivedQuantity' => ['required', 'integer', 'min:0', 'max:2147483647'], 'reference' => $reference, 'reason' => $reason],
            'handoff' => ['code' => ['required', 'string', 'size:12']],
            'issue' => ['type' => ['required', Rule::in(['missing', 'damaged', 'not_received'])], 'description' => $reason],
            'resolve' => ['resolutionType' => ['required', Rule::in(['refund', 'resolved'])], 'reason' => $reason, 'reference' => $reference],
            'record' => ['kind' => ['required', Rule::in(['carrier_due', 'supplier_settled', 'carrier_settled'])], 'amount' => [...$money, 'gt:0'], 'reason' => $reason, 'reference' => $reference],
            'cancel' => ['reason' => $reason],
            'simulate' => ['status' => ['required', Rule::in(['paid', 'failed'])]],
            default => [],
        };
    }

    public function messages(): array
    {
        return ['required' => 'Preencha este campo.', 'accepted' => 'Aceite as condições de compra.', 'after' => 'Indique uma data futura.',
            'paymentMethod.in' => 'Escolha um método de pagamento disponível.', 'paymentPhone.regex' => 'Use +258 seguido de 9 dígitos.'];
    }
}
