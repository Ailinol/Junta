<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\CommercialDate;
use App\Support\PlatformRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('deadline')) {
            $this->merge(['deadline' => CommercialDate::normalize($this->input('deadline'))]);
        }
    }

    public function authorize(): bool
    {
        if ($this->routeIs('organizador.campanhas.update')) {
            return $this->user()?->can('manage-campaign', $this->route('campaign')) ?? false;
        }

        return $this->user()?->can('organize-campaigns') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        $admin = $this->routeIs('admin.campanhas.*') && $this->user()->can('administer-platform');

        return [
            'organizerId' => [Rule::excludeIf(! $admin), 'required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where(fn ($q) => $q->where('is_organizer', true)->orWhere('is_admin', true)))],
            'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', Rule::in(PlatformRules::CATEGORIES)],
            'status' => ['required', Rule::in($admin ? PlatformRules::CAMPAIGN_STATUSES : ['rascunho', 'ativa'])],
            'unit' => ['required', 'string', 'max:60'], 'targetQuantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'estimatedPrice' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'deadline' => array_filter(['required', 'date', $this->isMethod('post') || $this->routeIs('organizador.campanhas.update') ? 'after:now' : null]),
            'location' => ['required', 'string', 'max:255'], 'deliveryMode' => ['required', Rule::in(PlatformRules::DELIVERY_MODES)],
            'imageUrl' => ['nullable', 'url:http,https', 'max:2048'], 'context' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Preencha este campo.', 'deadline.after' => 'Indique um prazo futuro.', 'organizerId.exists' => 'Escolha uma conta com acesso de organizador aprovado.', 'targetQuantity.min' => 'O objectivo deve ser superior a zero.'];
    }

    /** @return array<string,mixed> */
    public function campaignData(): array
    {
        $data = $this->validated();
        $owner = isset($data['organizerId']) ? User::query()->whereKey($this->integer('organizerId'))->firstOrFail() : $this->user();

        return ['organizer_id' => $owner->id, 'organizer_name' => $owner->name, 'title' => $data['title'], 'description' => $data['description'], 'category' => $data['category'], 'status' => $data['status'], 'unit' => $data['unit'], 'target_quantity' => $data['targetQuantity'], 'estimated_price' => $data['estimatedPrice'] ?? null, 'deadline' => $data['deadline'], 'location' => $data['location'], 'delivery_mode' => $data['deliveryMode'], 'image_url' => $data['imageUrl'] ?? null, 'tags' => empty($data['context']) ? [] : [$data['context']]];
    }
}
