<?php

namespace Meva\Api\V1\Requests\Order;

use Meva\Api\V1\Requests\ApiFormRequest;

class OrderCreateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sku' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],

            'customer.first_name' => ['required', 'string', 'max:120'],
            'customer.last_name' => ['required', 'string', 'max:120'],
            'customer.phone' => ['required', 'string', 'max:40'],
            'customer.email' => ['nullable', 'email', 'max:190'],
            'customer.address' => ['required', 'string', 'max:255'],
            'customer.city' => ['required', 'string', 'max:120'],
            'customer.postcode' => ['nullable', 'string', 'max:20'],
            'customer.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer.first_name.required' => 'Unesite ime.',
            'customer.last_name.required' => 'Unesite prezime.',
            'customer.phone.required' => 'Unesite broj telefona — kurir vas zove pre dostave.',
            'customer.address.required' => 'Unesite adresu za dostavu.',
            'customer.city.required' => 'Unesite grad.',
            'lines.required' => 'Korpa je prazna.',
        ];
    }
}
