<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cards.view') === true;
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'max:512'],
            'mode' => ['required', Rule::in(['verification', 'production', 'releasing'])],
        ];
    }
}
