<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SchoolSeeder::class);
        // About 11 weeks of attendance until yesterday, for the analytics pages (tests use SchoolSeeder only)
        $this->call(DemoAttendanceSeeder::class);
    }
}
