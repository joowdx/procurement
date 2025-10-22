<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required_if:disk,local', 'nullable', 'file', 'max:102400'], // 100MB
            'disk' => ['required', 'string', Rule::in(['local', 'external'])],
            'path' => ['required_if:disk,external', 'nullable', 'string', 'url', 'max:2048'],
            'folder_id' => ['nullable', 'string', Rule::exists('folders', 'id')],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['string', Rule::exists('folders', 'id')],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required_if' => 'A file is required when disk is local.',
            'path.required_if' => 'A URL path is required when disk is external.',
            'path.url' => 'The path must be a valid URL.',
            'disk.in' => 'The disk must be either local or external.',
        ];
    }
}
