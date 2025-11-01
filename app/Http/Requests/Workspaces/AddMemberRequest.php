<?php

namespace App\Http\Requests\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AddMemberRequest extends FormRequest
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
        $workspace = $this->current_workspace;

        return [
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'id'),
                Rule::unique('memberships', 'user_id')
                    ->where('workspace_id', $workspace->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.users' => ['nullable', 'boolean'],
            'permissions.files' => ['nullable', 'boolean'],
            'permissions.folders' => ['nullable', 'boolean'],
            'permissions.settings' => ['nullable', 'boolean'],
        ];
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
            'user_id.unique' => 'This user is already a member of the group.',
        ];
    }
}
