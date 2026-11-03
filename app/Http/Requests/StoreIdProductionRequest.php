<?php

namespace App\Http\Requests;

use App\Enums\IdIssuanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cards.manage') === true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'student_enrollment_id' => ['required', 'integer', Rule::exists('student_enrollments', 'id')->where('student_id', $this->input('student_id'))],
            'issuance_type' => ['required', Rule::enum(IdIssuanceType::class)],
            'existing_card_id' => ['nullable', 'integer', 'exists:id_cards,id'],
            'internal_notes' => ['nullable', 'string', 'max:1000', Rule::requiredIf($this->input('issuance_type') === IdIssuanceType::Other->value)],
        ];
    }

    public function messages(): array
    {
        return [
            'student_enrollment_id.required' => 'Select the enrollment that this ID request applies to.',
            'internal_notes.required' => 'Explain the other authorized reason before creating the request.',
        ];
    }
}
