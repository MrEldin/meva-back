<?php

namespace Meva\Api\V1\Requests;

use Dingo\Api\Http\FormRequest;

/**
 * Base request adding validated() to Dingo's form request.
 *
 * Dingo's FormRequest extends Illuminate\Http\Request rather than Laravel's own
 * FormRequest, so it validates but never exposes the validated payload. Reading
 * $request->all() instead would let a client set any column it likes, so the
 * input is narrowed to the keys the rules actually declare.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Get the request data covered by the validation rules.
     *
     * Only keys that were actually sent are returned, so "not supplied" stays
     * distinguishable from "sent as null".
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $keys = collect(array_keys($this->rules()))
            ->map(fn (string $rule): string => explode('.', $rule)[0])
            ->unique()
            ->all();

        return array_filter(
            $this->only($keys),
            fn ($value, string $key): bool => $this->has($key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
