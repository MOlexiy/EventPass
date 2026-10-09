<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'venue' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'cover_url' => ['nullable', 'url', 'max:500'],
            'ticket_types' => ['required', 'array', 'min:1', 'max:10'],
            'ticket_types.*.id' => ['sometimes', 'nullable', 'integer'],
            'ticket_types.*.name' => ['required', 'string', 'max:80'],
            'ticket_types.*.price' => ['required', 'integer', 'min:100', 'max:10000000'],
            'ticket_types.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'ticket_types.*.currency' => ['sometimes', Rule::in(['UAH', 'USD', 'EUR'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ticket_types.*.price.min' => 'The minimum ticket price is 1.00.'];
    }
}
