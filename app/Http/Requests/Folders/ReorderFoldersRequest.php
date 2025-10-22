<?php

namespace App\Http\Requests\Folders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReorderFoldersRequest extends FormRequest
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
            'folders' => ['required', 'array'],
            'folders.*.id' => ['required', 'string', Rule::exists('folders', 'id')],
            'folders.*.order' => ['required', 'integer', 'min:1'],
        ];
    }
    
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'folders.required' => 'Folders data is required.',
            'folders.*.id.required' => 'Folder ID is required.',
            'folders.*.id.exists' => 'One or more folders do not exist.',
            'folders.*.order.required' => 'Order is required for each folder.',
            'folders.*.order.integer' => 'Order must be an integer.',
            'folders.*.order.min' => 'Order must be at least 1.',
        ];
    }
}
