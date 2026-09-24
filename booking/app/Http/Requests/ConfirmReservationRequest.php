<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() -]{7,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'gstin' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_state_code' => ['nullable', 'digits:2'],
            'special_request' => ['nullable', 'string', 'max:2000'],
            'promo_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }
}
