<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Development admin credentials.
     *
     * Email:    admin@shabaka.com
     * Password: password
     *
     * Change this password before deploying to any shared environment.
     */
    private const ADMIN_EMAIL = 'admin@shabaka.com';

    private const ADMIN_PASSWORD = 'Aa123456';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Admin',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'role' => UserRole::ADMIN,
                'business_id' => null,
            ],
        );
    }
}
