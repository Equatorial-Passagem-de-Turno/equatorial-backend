<?php

namespace Database\Seeders;

use App\Models\Roles;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Baixa Tensão (BT)',
                'description' => 'Gestão de rede BT e consumidores',
            ],
            [
                'name' => 'Média Tensão (MT)',
                'description' => 'Operação de chaves e alimentadores',
            ],
            [
                'name' => 'Alta Tensão (AT)',
                'description' => 'Subestações e Linhas de Transmissão',
            ],
            [
                'name' => 'Eng. Pré-Operação',
                'description' => 'Planejamento e análise técnica',
            ]
        ];

        foreach ($roles as $role) {
            Roles::updateOrCreate(
                ['name' => $role['name']], 
                [
                    'description' => $role['description'],
                    'is_active' => true
                ]
            );
        }
    }
}