<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnavailableEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_number' => 'sometimes|string|max:255',
            'equipment_type' => 'sometimes|string|max:255',
            'feeder' => 'sometimes|string|max:255',
            'responsible_sector' => 'sometimes|string|max:255',
            'observations' => 'nullable|string',
            'deadline' => 'sometimes|date',
            'status' => 'sometimes|in:indisponivel,disponivel',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ];
    }
}
