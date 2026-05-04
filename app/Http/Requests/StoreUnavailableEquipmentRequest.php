<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnavailableEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Assumindo que a rota já estará protegida pelo middleware de autenticação
    }

    public function rules(): array
    {
        return [
            'shift_id' => 'required|exists:shifts,id',
            'equipment_number' => 'required|string|max:255',
            'equipment_type' => 'required|string|max:255',
            'feeder' => 'required|string|max:255',
            'responsible_sector' => 'required|string|max:255',
            'observations' => 'nullable|string',
            'deadline' => 'required|date',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_account.required_without' => 'É obrigatório informar a Conta Contrato ou o Número da Nota.',
            'note_number.required_without' => 'É obrigatório informar o Número da Nota ou a Conta Contrato.',
        ];
    }
}
