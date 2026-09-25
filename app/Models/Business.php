<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'vat_number',
        'commercial_registration'
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
