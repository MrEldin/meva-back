<?php

namespace Meva\Api\V1\Requests\Product;

use Meva\Api\V1\Requests\ApiFormRequest;

class ProductUpdateRequest extends ApiFormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', 'in:published,draft'],
            'product_type_id' => ['sometimes', 'required', 'integer', 'exists:lunar_product_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:lunar_brands,id'],
        ];
    }
}
