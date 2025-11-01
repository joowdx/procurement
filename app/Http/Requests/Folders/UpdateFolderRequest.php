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
        if (! Auth::check()) {
            return false;
        }

        // Check workspace access for the folder
        $folder = $this->route('folder');
        if ($folder && $folder->workspace_id) {
            $workspace = $folder->workspace;
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
        $folder = $this->route('folder');
        $folderId = $folder ? $folder->id : null;
        $parentId = $this->input('parent_id', $folder ? $folder->parent_id : null);

        return [
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('folders', 'id'),
                // Prevent folder from being its own parent
                Rule::notIn([$folderId]),
                // Prevent moving folder to its own descendant
                function ($attribute, $value, $fail) use ($folder) {
                    if ($value && $folder) {
                        $descendants = \App\Models\Folder::where('parent_id', $folder->id)->pluck('id')->toArray();
                        if (in_array($value, $descendants)) {
                            $fail('Cannot move a folder to its own descendant.');
                        }
                    }
                },
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('folders', 'name')
                    ->where('parent_id', $parentId)
                    ->whereNull('deleted_at')
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
