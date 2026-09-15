<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Meva\Entities\Permission\Models\Permission;
use Meva\Entities\Role\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who may do what.
 *
 * Four roles cover the shop: the owner, someone helping run it day to day,
 * someone who only looks at the numbers, and the customers themselves. The
 * seeder is idempotent, so it can be re-run whenever a permission is added.
 */
class AccessSeeder extends Seeder
{
    /**
     * Every permission the API checks, with the label the admin shows.
     */
    public const PERMISSIONS = [
        'analytics.view' => 'Pregled analitike',
        'orders.view' => 'Pregled porudžbina',
        'orders.manage' => 'Izmena porudžbina',
        'products.view' => 'Pregled proizvoda',
        'products.manage' => 'Izmena proizvoda',
        'customers.view' => 'Pregled kupaca',
        'marketing.manage' => 'Marketing alati',
        'users.manage' => 'Upravljanje korisnicima',
        'settings.manage' => 'Podešavanja prodavnice',
    ];

    /**
     * What each role may do. The owner gets everything.
     */
    public const ROLES = [
        'super-admin' => ['label' => 'Vlasnik', 'permissions' => '*'],
        'admin' => [
            'label' => 'Administrator',
            'permissions' => [
                'analytics.view', 'orders.view', 'orders.manage',
                'products.view', 'products.manage', 'customers.view', 'marketing.manage',
            ],
        ],
        'marketing' => [
            'label' => 'Marketing',
            'permissions' => ['analytics.view', 'orders.view', 'products.view', 'marketing.manage'],
        ],
        'customer' => ['label' => 'Kupac', 'permissions' => []],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard');

        foreach (self::PERMISSIONS as $name => $label) {
            Permission::query()->updateOrCreate(
                [Permission::NAME => $name, Permission::GUARD_NAME => $guard],
                [Permission::LABEL => $label],
            );
        }

        foreach (self::ROLES as $name => $role) {
            $model = Role::query()->updateOrCreate(
                [Role::NAME => $name, Role::GUARD_NAME => $guard],
                [Role::LABEL => $role['label']],
            );

            $model->syncPermissions(
                $role['permissions'] === '*'
                    ? array_keys(self::PERMISSIONS)
                    : $role['permissions']
            );
        }

        // The recruitment roles this skeleton shipped with have nothing to do
        // with a cosmetics shop; drop them once nobody holds them.
        DB::table('roles')->whereIn('name', ['applicant', 'hiring-manager'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
