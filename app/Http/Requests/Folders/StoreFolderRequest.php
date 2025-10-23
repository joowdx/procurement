<?php

namespace App\Http\Requests\Folders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
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
            'parent_id' => ['nullable', 'string', Rule::exists('folders', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('folders', 'name')
                    ->where(function ($query) {
                        return $query->where('parent_id', $this->input('parent_id'))
                            ->whereNull('deleted_at');
                    }),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The selected parent folder does not exist.',
            'name.required' => 'Folder name is required.',
            'name.max' => 'Folder name cannot exceed 255 characters.',
            'name.unique' => 'A folder with this name already exists in the same location.',
        ];
    }
}
