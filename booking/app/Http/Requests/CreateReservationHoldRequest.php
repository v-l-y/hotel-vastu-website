<?php

namespace App\Http\Requests;

class CreateReservationHoldRequest extends SearchAvailabilityRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
    }
}
