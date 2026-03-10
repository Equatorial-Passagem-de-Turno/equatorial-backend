<?php

namespace Database\Seeders;

use App\Models\OperationDesk;
use Illuminate\Database\Seeder;

class OperationDeskSeeder extends Seeder
{
    public function run(): void
    {
        $desks = [
            ['code' => 'MESA-01', 'name' => 'MCZ I', 'location' => 'Maceió'],
            ['code' => 'MESA-02', 'name' => 'MCZ II', 'location' => 'Maceió'],
            ['code' => 'MESA-03', 'name' => 'DMG / SDI', 'location' => 'Maceió'],
            ['code' => 'MESA-04', 'name' => 'RLU / SMC', 'location' => 'Maceió'],
            ['code' => 'MESA-05', 'name' => 'DMG/SDI/PND', 'location' => 'Maceió'],
            ['code' => 'MESA-06', 'name' => 'LESTE/OESTE', 'location' => 'Maceió'],
            ['code' => 'MESA-07', 'name' => 'MCZ I/RLU (RD LESTE 1)', 'location' => 'Maceió'],
            ['code' => 'MESA-08', 'name' => 'OUTRAS', 'location' => 'Maceió'],
        ];

        foreach ($desks as $desk) {
            OperationDesk::updateOrCreate(
                ['code' => $desk['code']],
                [
                    'name' => $desk['name'],
                    'location' => $desk['location'],
                    'is_active' => true
                ]
            );
        }
    }
}