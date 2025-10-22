<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Handle restore request
        if ($this->has('restore')) {
            return [
                'restore' => ['required', 'boolean'],
            ];
        }

        // Handle file replacement
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'max:102400'], // 100MB, nullable for external
            'disk' => ['sometimes', 'string', Rule::in(['local', 'external'])],
            'path' => ['required_if:disk,external', 'nullable', 'string', 'url', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'path.required_if' => 'A URL path is required when disk is external.',
            'path.url' => 'The path must be a valid URL.',
            'disk.in' => 'The disk must be either local or external.',
        ];
    }
}
