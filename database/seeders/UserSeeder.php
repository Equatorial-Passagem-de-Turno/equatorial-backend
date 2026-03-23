<?php

namespace Database\Seeders;

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
                'active' => true,
            ],
            [
                'name' => 'Carlos Oliveira',
                'email' => 'carlos.oliveira@eqdemo.local',
                'role' => 'operador',
                'voltage_level' => 'MT',
                'active' => true,
            ],
            [
                'name' => 'Fernanda Souza',
                'email' => 'fernanda.souza@eqdemo.local',
                'role' => 'operador',
                'voltage_level' => 'AT',
                'active' => true,
            ],
            [
                'name' => 'Ricardo Mendes',
                'email' => 'ricardo.mendes@eqdemo.local',
                'role' => 'supervisor',
                'voltage_level' => 'MT',
                'active' => true,
            ],
            [
                'name' => 'Patricia Lima',
                'email' => 'patricia.lima@eqdemo.local',
                'role' => 'supervisor',
                'voltage_level' => 'BT',
                'active' => true,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => $data['role'],
                    'voltage_level' => $data['voltage_level'],
                    'active' => $data['active'],
                ]
            );
        }
    }
}
