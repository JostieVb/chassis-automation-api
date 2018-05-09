<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
        [
            'name' => 'Admin',
            'email' => 'admin@chassisautomation.com',
            'password' => bcrypt('admin'),
            'permissions' => 'automation,products,processes,dashboard,forms,data-tables'
        ],
        [
            'name' => 'Logistics',
            'email' => 'testaccount@chassisautomation.com',
            'password' => bcrypt('admin'),
            'permissions' => 'dashboard,entries'
        ],
        [
            'name' => 'Supplier',
            'email' => 'supplier@chassisautomation.com',
            'password' => bcrypt('admin'),
            'permissions' => 'products,entries'
        ]
        ]);
    }
}
