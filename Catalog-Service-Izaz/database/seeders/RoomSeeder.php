<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use Illuminate\Support\Str;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Room::create([
            'id' => Str::uuid(),
            'name' => 'Deluxe Suite Sea View',
            'location' => 'Bali',
            'price' => 1500000,
            'description' => 'A luxury room with a beautiful view of the Indian Ocean.',
            'facilities' => json_encode(['Wi-Fi', 'AC', 'Mini Bar', 'TV']),
            'status' => 'AVAILABLE',
        ]);

        Room::create([
            'id' => Str::uuid(),
            'name' => 'Superior Garden Room',
            'location' => 'Bandung',
            'price' => 750000,
            'description' => 'Comfortable room surrounded by tropical gardens.',
            'facilities' => json_encode(['Wi-Fi', 'AC', 'Coffee Maker']),
            'status' => 'AVAILABLE',
        ]);
    }
}
