<?php

namespace Database\Seeders;

use App\Models\OperationDesk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Test User',
                'email' => 'teste@teste.com',
                'role' => 'operador',
                'voltage_level' => 'BT',
                'operation_desk_name' => 'MCZ I',
                'active' => true,
            ],
            [
                'name' => 'Carlos Oliveira',
                'email' => 'carlos.oliveira@eqdemo.local',
                'role' => 'operador',
                'voltage_level' => 'MT',
                'operation_desk_name' => 'MCZ II',
                'active' => true,
            ],
            [
                'name' => 'Fernanda Souza',
                'email' => 'fernanda.souza@eqdemo.local',
                'role' => 'operador',
                'voltage_level' => 'AT',
                'operation_desk_name' => 'DMG / SDI',
                'active' => true,
            ],
            [
                'name' => 'Ricardo Mendes',
                'email' => 'ricardo.mendes@eqdemo.local',
                'role' => 'supervisor',
                'voltage_level' => 'MT',
                'operation_desk_name' => 'RLU / SMC',
                'active' => true,
            ],
            [
                'name' => 'Patricia Lima',
                'email' => 'patricia.lima@eqdemo.local',
                'role' => 'supervisor',
                'voltage_level' => 'BT',
                'operation_desk_name' => 'OUTRAS',
                'active' => true,
            ],
        ];

        foreach ($users as $data) {
            $deskId = OperationDesk::query()
                ->where('is_active', true)
                ->where('name', $data['operation_desk_name'])
                ->value('id');

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => $data['role'],
                    'voltage_level' => $data['voltage_level'],
                    'operation_desk_id' => $deskId,
                    'active' => $data['active'],
                ]
            );
        }
    }
}
