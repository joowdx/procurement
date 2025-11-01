<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateFileRequest extends FormRequest
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
        // Handle restore request
        if ($this->has('restore')) {
            return [
                'restore' => ['required', 'boolean'],
            ];
        }

        // Handle file replacement
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'max:102400'], // 100MB, nullable for external
            'disk' => ['nullable', 'string', Rule::in(['local', 'external'])],
            'path' => ['nullable', 'string', 'url', 'max:2048'],
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
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

            curl_close($ch);

            if ($httpCode !== 200) {
                $fail('The file URL is not accessible or does not exist.');

                return;
            }

            // Check if it's likely a file (not HTML)
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
            'path.required_if' => 'A URL path is required when disk is external.',
            'path.url' => 'The path must be a valid URL.',
            'disk.in' => 'The disk must be either local or external.',
        ];
    }
}
