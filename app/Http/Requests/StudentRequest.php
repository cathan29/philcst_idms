<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['student_number', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->mapWithKeys(fn ($field) => [$field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field)])
            ->all());
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'student_number' => ['required', 'string', 'max:64', Rule::unique('students')->ignore($student)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'withdrawn'])],
        ];
    }
}
