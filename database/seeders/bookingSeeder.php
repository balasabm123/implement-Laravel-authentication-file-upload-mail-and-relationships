<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class bookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    { 
        \DB::table('booking')->insert([
            'app_ref_id' => Str::random(10),
            'name' => Str::random(10),
            'email' => Str::random(10).'@gmail.com',
            'phone' => (string) random_int(1000000000, 9999999999),
            'created_at' => now(),
            'updated_at' => now(),  
        ]);
    }
}
