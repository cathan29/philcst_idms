<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentImportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'academic_term_id' => ['required', 'integer', 'exists:academic_terms,id'],
            'coverage' => ['required', 'in:all,specific'],
            'coverage_program_id' => ['nullable', 'required_if:coverage,specific', 'integer', 'exists:programs,id'],
            'csv' => ['required', 'file', 'extensions:csv', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:'.config('idms.import_max_kilobytes', 25600)],
        ];
    }

    public function messages(): array
    {
        $megabytes = number_format(config('idms.import_max_kilobytes', 25600) / 1024);

        return [
            'academic_term_id.required' => 'Select an academic year and semester.',
            'academic_term_id.exists' => 'The selected academic term is no longer available.',
            'coverage.required' => 'Select an import coverage.',
            'coverage.in' => 'Select All Programs in CSV or Single Program.',
            'coverage_program_id.required_if' => 'Select the program for this Single Program import.',
            'coverage_program_id.exists' => 'The selected program is no longer available.',
            'csv.required' => 'Select an official enrollment CSV file.',
            'csv.extensions' => 'The enrollment file must use the .csv extension.',
            'csv.mimetypes' => 'The selected file is not a supported CSV file.',
            'csv.max' => "The CSV file must not exceed {$megabytes} MB.",
        ];
    }
}
