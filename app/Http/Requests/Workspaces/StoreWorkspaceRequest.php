<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array(Auth::user()?->role, ['admin', 'root']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $slug = $this->input('slug') ?? \Illuminate\Support\Str::slug($value);
                    $exists = \App\Models\Workspace::where('slug', $slug)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        $fail('A workspace with this name already exists.');
                    }
                },
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('workspaces', 'slug')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'settings' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Workspace name is required.',
            'name.max' => 'Workspace name cannot exceed 255 characters.',
            'slug.regex' => 'Slug must contain only lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This slug is already taken.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
