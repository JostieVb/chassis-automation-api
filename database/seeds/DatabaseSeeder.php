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
            'email' => 'admin@chassis-automation.com',
            'password' => bcrypt('admin'),
            'permissions' => 'dashboard,entries,automation,processes,forms,data-tables'
        ],
        [
            'name' => 'Logistics',
            'email' => 'logistics@chassis-automation.com',
            'password' => bcrypt('admin'),
            'permissions' => 'dashboard,entries'
        ],
        [
            'name' => 'Supplier',
            'email' => 'supplier@chassis-automation.com',
            'password' => bcrypt('admin'),
            'permissions' => 'dashboard,entries,products'
        ]
        ]);

        DB::table('oauth_clients')->insert([
            [
                'id' => 1,
                'name' => 'Chassis-automation Personal Access Client',
                'secret' => 'MhKYdb3muSmOuavo1Zwslh1ZFAPVPx8rIVKL2RAA',
                'redirect' => 'http://localhost',
                'personal_access_client' => 1,
                'password_client' => 0,
                'revoked' => 0
            ],
            [
                'id' => 2,
                'name' => 'Chassis-automation Password Grant Client',
                'secret' => 'SUzG7xjcVOzJIXOPYWqyGkQy4Uu6gpHpKEr9ee0D',
                'redirect' => 'http://localhost',
                'personal_access_client' => 0,
                'password_client' => 1,
                'revoked' => 0
            ]
        ]);

        DB::table('oauth_personal_access_clients')->insert([
            [
                'id' => 1,
                'client_id' => 1
            ]
        ]);
    }
}
