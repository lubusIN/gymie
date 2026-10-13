<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update settings JSON payload.
 *
 * This stays permissive so installations can extend the settings payload
 * without changing the core validation rules.
 */
class SettingsUpdateRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'general' => ['sometimes', 'array'],
            'invoice' => ['sometimes', 'array'],
            'member' => ['sometimes', 'array'],
            'charges' => ['sometimes', 'array'],
            'expenses' => ['sometimes', 'array'],
            'subscriptions' => ['sometimes', 'array'],
            'payments' => ['sometimes', 'array'],
            'notifications' => ['sometimes', 'array'],
            'notifications.email' => ['sometimes', 'array'],
        ];
    }
}
