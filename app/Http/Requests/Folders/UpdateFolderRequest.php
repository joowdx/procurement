<?php

namespace App\Http\Requests\Folders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
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
        $folderId = $this->route('folder')->id ?? null;

        return [
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('folders', 'id'),
                // Prevent folder from being its own parent
                Rule::notIn([$folderId]),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('folders', 'name')
                    ->where(function ($query) {
                        $folder = $this->route('folder');
                        $parentId = $this->input('parent_id', $folder->parent_id);

                        return $query->where('parent_id', $parentId)
                            ->whereNull('deleted_at');
                    })
                    ->ignore($folderId),
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
            'parent_id.not_in' => 'A folder cannot be its own parent.',
            'name.max' => 'Folder name cannot exceed 255 characters.',
            'name.unique' => 'A folder with this name already exists in the same location.',
        ];
    }
}
