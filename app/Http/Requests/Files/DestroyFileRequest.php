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
        $file = $this->route('file');
        
        // User must be authenticated and file must not be locked
        return Auth::check() && !$file->locked;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the error messages for authorization failures.
     */
    public function failedAuthorization()
    {
        $file = $this->route('file');
        
        if ($file && $file->locked) {
            abort(403, 'This file is locked and cannot be deleted.');
        }
        
        parent::failedAuthorization();
    }
}
