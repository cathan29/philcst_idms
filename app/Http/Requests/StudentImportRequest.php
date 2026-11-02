<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('imports.manage') === true;
    }

    public function rules(): array
    {
        return ['csv' => ['required', 'file', 'extensions:csv,txt', 'mimes:csv,txt', 'max:10240']];
    }
}
