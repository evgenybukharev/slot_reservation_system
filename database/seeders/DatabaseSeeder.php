<?php

namespace Database\Seeders;

use App\Models\Slot;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        foreach (range(1, 10) as $item) {
            $capacity = rand(1, 10);
            Slot::create([
                'capacity' => $capacity,
                'remaining' => $capacity,
            ]);
        }
    }
}
