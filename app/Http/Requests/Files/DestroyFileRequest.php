<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class DestroyFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        // Check workspace access for the file
        $fileId = $this->route('file');
        if (is_object($fileId)) {
            $fileId = $fileId->id;
        }
        $file = \App\Models\File::withTrashed()->find($fileId);
        if ($file && $file->workspace_id) {
            $workspace = $file->workspace;
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

            // Check if user is a member with files permission
            $membership = \App\Models\Membership::where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->first();
            if (! $membership) {
                return false;
            }

            $permissions = $membership->permissions ?? [];

            return $permissions['files'] ?? true;
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
        $file = $this->route('file');

        // If file is trashed, require password for force delete
        if ($file && $file->trashed()) {
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
            'current_password.required' => 'Your password is required to permanently delete this file.',
            'current_password.current_password' => 'The provided password is incorrect.',
        ];
    }
}
