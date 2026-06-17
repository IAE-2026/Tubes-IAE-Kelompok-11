<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Addon;
use Illuminate\Support\Str;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        Addon::create([
            'id' => Str::uuid(),
            'name' => 'Breakfast Buffet',
            'price' => 150000,
            'description' => 'Unlimited access to the morning buffet.',
        ]);

        Addon::create([
            'id' => Str::uuid(),
            'name' => 'Travel Insurance',
            'price' => 50000,
            'description' => 'Coverage for your stay.',
        ]);

        Addon::create([
            'id' => Str::uuid(),
            'name' => 'Airport Pickup',
            'price' => 200000,
            'description' => 'Shuttle service from airport to hotel.',
        ]);
    }
}
