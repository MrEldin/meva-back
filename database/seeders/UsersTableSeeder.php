<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Meva\Entities\User\Models\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // The owner's account. Credentials come from the environment so a
        // production database is never seeded with a published default.
        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@meva.life')],
            [
                'first_name' => env('ADMIN_FIRST_NAME', 'Meva'),
                'last_name' => env('ADMIN_LAST_NAME', 'Admin'),
                'password' => env('ADMIN_PASSWORD', str()->random(24)),
            ],
        );

        $admin->syncRoles('super-admin');
    }
}
