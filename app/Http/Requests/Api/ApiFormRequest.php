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
 *   422 { "message": "...", "errors": { "field": ["..."] } }
 *
 * Authorisation is deliberately left unimplemented here. When the tenancy and
 * role model is defined, authorize() is where per-endpoint policies will be
 * enforced, keeping controllers thin.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permissions are not defined yet. Returning true keeps validation
        // active and reachable while auth rules are still being decided.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
        // Intentionally empty. Subclasses normalise their own input
        // (trimming, lowercasing, etc.) so behaviour stays predictable.
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
