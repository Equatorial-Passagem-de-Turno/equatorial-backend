<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCircuitSwitchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feeder' => 'sometimes|string|max:255',
            'equipment' => 'sometimes|string|max:255',
            'affected_clients' => 'nullable|integer|min:0',
            'responsible_sector' => 'sometimes|string|max:255',
            'reason' => 'sometimes|string',
            'observations' => 'nullable|string',
            'deadline' => 'sometimes|date',
            'new_deadline' => 'nullable|date',
            'status' => 'sometimes|in:manobrado,normalizado',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ];
    }
}
