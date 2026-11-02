<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentImportRequest extends FormRequest
{
    public function rules(): array
    {
        return ['academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'], 'coverage' => ['required', 'in:all,specific'], 'coverage_program_id' => ['nullable', 'required_if:coverage,specific', 'integer', 'exists:programs,id'], 'csv' => ['required', 'file', 'extensions:csv', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:'.config('idms.import_max_kilobytes', 25600)]];
    }
}
