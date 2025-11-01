<?php

namespace App\Http\Requests\Folders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReorderFoldersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        // Check workspace access for the folders
        $folderIds = array_column($this->input('folders', []), 'id');
        if (! empty($folderIds)) {
            $folder = \App\Models\Folder::find($folderIds[0]);
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
            'folders' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    // Check all folders have the same parent
                    $folderIds = array_column($value, 'id');
                    $folders = \App\Models\Folder::whereIn('id', $folderIds)->get();

                    if ($folders->isEmpty()) {
                        return;
                    }

                    $firstParentId = $folders->first()->parent_id;

                    foreach ($folders as $folder) {
                        if ($folder->parent_id !== $firstParentId) {
                            $fail('All folders must have the same parent to be reordered together.');

                            return;
                        }
                    }
                },
            ],
            'folders.*.id' => ['required', 'string', Rule::exists('folders', 'id')],
            'folders.*.order' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'folders.required' => 'Folders data is required.',
            'folders.*.id.required' => 'Folder ID is required.',
            'folders.*.id.exists' => 'One or more folders do not exist.',
            'folders.*.order.required' => 'Order is required for each folder.',
            'folders.*.order.integer' => 'Order must be an integer.',
            'folders.*.order.min' => 'Order must be at least 1.',
        ];
    }
}
