<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Brand;

class BrandSeeder extends Seeder
{
    /**
     * Networking-equipment manufacturers.
     *
     * @var array<int, string>
     */
    private const BRANDS = [
        'Cisco',
        'Juniper',
        'Aruba',
        'Ubiquiti',
        'Fortinet',
        'Palo Alto Networks',
        'MikroTik',
        'Netgear',
        'TP-Link',
        'HPE',
        'Dell',
        'Huawei',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::BRANDS as $name) {
            Brand::firstOrCreate(['name' => $name]);
        }
    }
}
