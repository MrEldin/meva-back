<?php

namespace Meva\Api\V1\Requests\User;

use Meva\Api\V1\Requests\ApiFormRequest;

/**
 * Opening a customer account.
 */
class RegisterRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Nalog sa ovom e-mail adresom već postoji.',
            'password.confirmed' => 'Lozinke se ne poklapaju.',
            'password.min' => 'Lozinka mora imati bar 8 karaktera.',
        ];
    }
}
