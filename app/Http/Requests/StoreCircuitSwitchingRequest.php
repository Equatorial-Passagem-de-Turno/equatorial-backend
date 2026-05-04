<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircuitSwitchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => 'required|exists:shifts,id',
            'feeder' => 'required|string|max:255',
            'equipment' => 'required|string|max:255',
            'affected_clients' => 'nullable|integer|min:0',
            'responsible_sector' => 'required|string|max:255',
            'reason' => 'required|string',
            'observations' => 'nullable|string',
            'deadline' => 'required|date',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ];
    }
}
