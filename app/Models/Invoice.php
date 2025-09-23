<?php

namespace App\Models;

use App\Models\Traits\Tenanted;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use Tenanted;

    protected $fillable = [
        'name',
        'tenant_id',
    ];
}
