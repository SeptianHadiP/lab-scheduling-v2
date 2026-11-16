<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\TimeSlot;
use Illuminate\Database\Seeder;

class TimeSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $slots = [
            ['name' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '09:30', 'sort_order' => 1],
            ['name' => 'Sesi 2', 'start_time' => '09:45', 'end_time' => '13:15', 'sort_order' => 2],
            ['name' => 'Sesi 3', 'start_time' => '13:30', 'end_time' => '16:00', 'sort_order' => 3],
            ['name' => 'Sesi 4', 'start_time' => '16:15', 'end_time' => '18:45', 'sort_order' => 4],
        ];

        foreach ($slots as $slot) {
            TimeSlot::updateOrCreate(['name' => $slot['name']], $slot);
        }
    }
}
