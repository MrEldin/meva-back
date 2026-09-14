<?php

namespace Meva\Api\V1\Requests\Product;

use Meva\Api\V1\Requests\ApiFormRequest;

class ProductCreateRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:published,draft'],
            'product_type_id' => ['required', 'integer', 'exists:lunar_product_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:lunar_brands,id'],

            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.currency_id' => ['nullable', 'integer', 'exists:lunar_currencies,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'variants.*.price.required' => 'Each variant needs a price, in minor units.',
        ];
    }
}
