<?php

namespace Meva\Api\V1\Requests\Product;

use Meva\Api\V1\Requests\ApiFormRequest;

/**
 * What goes in a set: the parts and how many of each.
 */
class SetItemsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'exists:lunar_products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Set mora da ima bar jedan proizvod.',
            'items.min' => 'Set mora da ima bar jedan proizvod.',
            'items.*.product_id.exists' => 'Neki od izabranih proizvoda ne postoji.',
        ];
    }
}
