<?php

namespace App\Http\Requests\Folders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DestroyFolderRequest extends FormRequest
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
        $folder = $this->route('folder');

        // If folder is trashed, require password for force delete
        if ($folder && $folder->trashed()) {
            return [
                'current_password' => ['required', 'string', 'current_password'],
            ];
        }

        // No validation needed for soft delete
        return [];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Your password is required to permanently delete this folder.',
            'current_password.current_password' => 'The provided password is incorrect.',
        ];
    }
}
