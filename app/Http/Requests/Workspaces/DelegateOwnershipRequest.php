<?php

namespace App\Http\Requests\Workspaces;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DelegateOwnershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        // Only admin can delegate ownership
        return $user->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $workspace = $this->route('group');

        return [
            'new_owner_id' => [
                'required',
                'string',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) use ($workspace) {
                    // Check if user is a member of the group
                    if (! $workspace->users()->wherePivot('user_id', $value)->exists()) {
                        $fail('The new owner must be a member of the group.');
                    }
                },
            ],
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'new_owner_id.required' => 'New owner is required.',
            'new_owner_id.exists' => 'Selected user does not exist.',
            'password.required' => 'Password is required.',
            'password.current_password' => 'The password is incorrect.',
        ];
    }
}
