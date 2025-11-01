<?php

namespace App\Http\Requests\Workspaces;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->current_workspace;
        $user = Auth::user();

        // Workspace owner or member with settings permission
        return $workspace->user_id === $user->id ||
               $user->role === 'root' ||
               $this->hasPermission($user, $workspace, 'settings');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $workspace = $this->current_workspace;

        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                // Check if slug generated from name would conflict
                function ($attribute, $value, $fail) use ($workspace) {
                    if ($value) {
                        $generatedSlug = \Illuminate\Support\Str::slug($value);
                        $exists = Workspace::where('slug', $generatedSlug)
                            ->whereNull('deleted_at')
                            ->where('id', '!=', $workspace->id)
                            ->exists();
                        if ($exists) {
                            $fail('A workspace with this name already exists.');
                        }
                    }
                },
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('workspaces', 'slug')
                    ->whereNull('deleted_at')
                    ->ignore($workspace),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
            'new_owner_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($workspace) {
                    if ($value && $value === $workspace->user_id) {
                        $fail('The new owner must be different from the current owner.');
                    }
                },
            ],
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
            'name.max' => 'Workspace name cannot exceed 255 characters.',
            'slug.regex' => 'Slug must contain only lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This slug is already taken.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
