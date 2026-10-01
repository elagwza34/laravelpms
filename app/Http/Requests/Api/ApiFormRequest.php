<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base class for every API form request.
 *
 * Centralises the validation contract so all endpoints fail in exactly the same
 * shape:
 *
 *   422 { "message": "...", "error_code": "validation_failed", "errors": {...} }
 *
 * Authorisation is intentionally not decided here. Endpoint permissions are
 * declared on the route via the `permission` middleware, and object-level checks
 * belong in policies. Keeping the two concerns separate avoids the common bug
 * where a request validates its input but forgets to check who is asking.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function prepareForValidation(): void
    {
        // Subclasses normalise their own input.
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => $validator->errors()->first(),
                'error_code' => 'validation_failed',
                'errors' => $validator->errors()->toArray(),
            ], 422)
        );
    }
}
