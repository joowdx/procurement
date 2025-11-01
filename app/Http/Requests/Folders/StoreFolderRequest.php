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
        if (! Auth::check()) {
            return false;
        }

        // Check workspace access if workspace_id is provided
        $workspaceId = $this->input('workspace_id');
        if ($workspaceId) {
            $workspace = \App\Models\Workspace::find($workspaceId);
            if (! $workspace) {
                return false;
            }

            $user = Auth::user();

            // Root users have access to all workspaces
            if ($user->role === 'root') {
                return true;
            }

            // Workspace owner always has access
            if ($workspace->user_id === $user->id) {
                return true;
            }

            // Check if user is a member with folders permission
            $membership = \App\Models\Membership::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->first();
            if (! $membership) {
                return false;
            }

            $permissions = $membership->permissions ?? [];

            return $permissions['folders'] ?? true;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'string', Rule::exists('workspaces', 'id')],
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
