<?php

namespace Meva\Api\V1\Requests\Product;

/**
 * A new set: its own name and price, and the parts it is made of.
 */
class SetCreateRequest extends SetItemsRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:published,draft'],
            // Dinars; left out, the set costs what its parts cost together.
            'price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'sku' => ['nullable', 'string', 'max:255'],
            ...parent::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Set mora da ima naziv.',
            ...parent::messages(),
        ];
    }
}
