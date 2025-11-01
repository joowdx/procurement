<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
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
        return [
            'workspace_id' => ['required', 'string', Rule::exists('workspaces', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => [
                'required_if:disk,local',
                'nullable',
                'file',
                'max:102400', // 100MB
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,zip,rar',
            ],
            'disk' => ['required', 'string', Rule::in(['local', 'external'])],
            'path' => [
                'required_if:disk,external',
                'nullable',
                'string',
                'url',
                'max:2048',
                function ($attribute, $value, $fail) {
                    if ($this->input('disk') === 'external' && $value) {
                        $this->validateExternalFile($value, $fail);
                    }
                },
            ],
            'folder_id' => ['nullable', 'string', Rule::exists('folders', 'id')],
            'folder_ids' => ['nullable', 'array'],
            'folder_ids.*' => ['string', Rule::exists('folders', 'id')],
        ];
    }

    /**
     * Validate that the external URL points to a downloadable file.
     */
    protected function validateExternalFile(string $url, callable $fail): void
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->head($url);

            if (! $response->successful()) {
                $fail('The file URL is not accessible or does not exist.');

                return;
            }

            // Check if it's likely a file (not HTML)
            $contentType = $response->header('Content-Type');
            if ($contentType && str_starts_with($contentType, 'text/html')) {
                $fail('The URL does not point to a downloadable file.');

                return;
            }
        } catch (\Exception $e) {
            $fail('Unable to verify the file URL: '.$e->getMessage());
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required_if' => 'A file is required when disk is local.',
            'path.required_if' => 'A URL path is required when disk is external.',
            'path.url' => 'The path must be a valid URL.',
            'disk.in' => 'The disk must be either local or external.',
        ];
    }
}
