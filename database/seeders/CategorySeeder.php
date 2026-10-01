<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Networking-equipment product categories.
     *
     * @var array<int, string>
     */
    private const CATEGORIES = [
        'Switches',
        'Routers',
        'Firewalls',
        'Access Points',
        'Network Accessories',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
