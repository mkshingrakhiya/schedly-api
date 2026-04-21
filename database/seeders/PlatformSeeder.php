<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['slug' => 'instagram', 'name' => 'Instagram'],
            ['slug' => 'facebook', 'name' => 'Facebook'],
        ];

        foreach ($platforms as $platform) {
            Platform::query()->updateOrCreate(
                ['slug' => $platform['slug']],
                ['name' => $platform['name']],
            );
        }
    }
}
