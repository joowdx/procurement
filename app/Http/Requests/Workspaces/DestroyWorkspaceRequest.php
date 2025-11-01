<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DestroyWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->route('group');
        $user = Auth::user();

        // Only group owner can delete
        return $workspace->user_id === $user->id || $user->role === 'root';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $workspace = $this->route('group');

        return [
            'password' => [
                Rule::requiredIf($workspace->trashed()),
                'string',
                'current_password',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'password.required_if' => 'Password is required to permanently delete this group.',
            'password.current_password' => 'The password is incorrect.',
        ];
    }
}
