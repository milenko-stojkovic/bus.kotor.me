<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->insert([
            [
                'username' => 'admin',
                'password' => '$2y$12$20bGkzmAY8YvSQvrF87IlOXqGmso/7DftiZv6cYWndqNWOUTYYttO',
                'email' => 'bus@kotor.me',
                'control_access' => false,
                'admin_access' => true,
                'created_at' => now(),
            ],
            [
                'username' => 'control',
                'password' => '$2y$12$bF0f7kVuyLzNz9nZ1.G9VuLgdqmJnZI7tsPo7AIgh5iiuNRsFzJ0q',
                'email' => 'controlbus@kotor.me',
                'control_access' => true,
                'admin_access' => false,
                'created_at' => now(),
            ],
        ]);
    }
}