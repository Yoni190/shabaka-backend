<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterService
{
    public function register(array $data): User
    {
        //Business and business user must be created atomically.
        return DB::transaction(function () use ($data) {
            $business = Business::create([
                'name' => $data['business_name'],
                'phone' => $data['phone'] ?? null,
                'vat_number' => $data['vat_number'] ?? null,
                'commercial_registration' => $data['commercial_registration'] ?? null,
            ]);

            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => UserRole::BUSINESS_CUSTOMER,
                'business_id' => $business->id,
            ]);
        });
    }
}
