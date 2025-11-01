<?php

namespace App\Http\Requests\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateMemberPermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->current_workspace;
        $user = Auth::user();

        // Workspace owner, member with users permission, or root
        return $workspace->user_id === $user->id ||
               $user->role === 'root' ||
               $this->hasPermission($user, $workspace, 'users');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.users' => ['required', 'boolean'],
            'permissions.files' => ['required', 'boolean'],
            'permissions.folders' => ['required', 'boolean'],
            'permissions.settings' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workspace = $this->current_workspace;
            $user = $this->route('user');

            // Check if user is actually a member
            if (! $workspace->users()->wherePivot('user_id', $user->id)->exists()) {
                $validator->errors()->add('user', 'This user is not a member of the group.');
            }

            // Cannot modify group owner permissions
            if ($workspace->user_id === $user->id) {
                $validator->errors()->add('user', 'Cannot modify group owner permissions.');
            }
        });
    }

    /**
     * Check if user has specific permission.
     */
    private function hasPermission($user, Workspace $workspace, string $permission): bool
    {
        if ($user->role === 'root') {
            return true;
        }

        if ($workspace->user_id === $user->id) {
            return true;
        }

        $membership = $workspace->users()->wherePivot('user_id', $user->id)->first();
        if (! $membership) {
            return false;
        }

        $permissions = $membership->pivot->permissions ?? [];

        return $permissions[$permission] ?? ($permission !== 'users');
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User is required.',
            'user_id.exists' => 'Selected user does not exist.',
            'permissions.required' => 'Permissions are required.',
            'permissions.users.required' => 'Users permission is required.',
            'permissions.files.required' => 'Files permission is required.',
            'permissions.folders.required' => 'Folders permission is required.',
            'permissions.settings.required' => 'Settings permission is required.',
        ];
    }
}
