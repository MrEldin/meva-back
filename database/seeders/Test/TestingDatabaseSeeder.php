<?php

namespace Database\Seeders\Test;

use Database\Seeders\AccessSeeder;
use Illuminate\Database\Seeder;
use Meva\Entities\Permission\Models\Permission;
use Meva\Entities\Role\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(\Database\Seeders\LunarBaselineSeeder::class);
        $this->call(RolesTableTestSeeder::class);
        $this->call(PermissionsTableTestSeeder::class);

        // The owner can do everything the API actually checks for, so an
        // admin endpoint can be tested as the owner without naming each
        // permission in the test.
        $guard = config('auth.defaults.guard');
        $owner = Role::query()->where(Role::NAME, 'super-admin')->where(Role::GUARD_NAME, $guard)->first();

        foreach (array_keys(AccessSeeder::PERMISSIONS) as $name) {
            $permission = Permission::query()->firstOrCreate(
                [Permission::NAME => $name, Permission::GUARD_NAME => $guard],
                [Permission::LABEL => AccessSeeder::PERMISSIONS[$name]],
            );
            $owner?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
