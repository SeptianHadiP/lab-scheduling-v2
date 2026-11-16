<?php

namespace Database\Seeders;

use App\Models\Lab;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labs = [
            ['name' => 'Laboratorium Komputer Data Science', 'code' => 'DS'],
            ['name' => 'Laboratorium Komputer Software Development', 'code' => 'SD'],
            ['name' => 'Laboratorium Komputer Network & Cyber Security', 'code' => 'NCS'],
        ];

        foreach ($labs as $lab) {
            \App\Models\Lab::updateOrCreate(['code' => $lab['code']], $lab);
        }
    }
}
