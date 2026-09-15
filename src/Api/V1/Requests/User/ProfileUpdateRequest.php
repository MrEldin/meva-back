<?php

namespace Meva\Api\V1\Requests\User;

use Meva\Api\V1\Requests\ApiFormRequest;

/**
 * Changing your own account details.
 */
class ProfileUpdateRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|required|string|max:60',
            'last_name' => 'sometimes|required|string|max:60',
            'email' => 'sometimes|required|email|max:190|unique:users,email,'.auth()->id(),
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|required|string|min:10|confirmed',
        ];
    }
}
