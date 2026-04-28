<?php

namespace Database\Seeders;

use App\Enums\Role as RoleSlug;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::query()->firstOrCreate(
            ['slug' => RoleSlug::CUSTOMER->value],
            [
                'name' => 'Customer',
                'description' => 'Creates and manages their own scheduled content.',
            ]
        );
    }
}
