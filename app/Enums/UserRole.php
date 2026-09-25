<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case BUSINESS_CUSTOMER = 'business_customer';
}