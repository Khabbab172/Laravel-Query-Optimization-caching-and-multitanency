<?php

namespace App\Models;

use App\Models\Traits\Tenanted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormData extends Model
{
    use HasFactory , Tenanted;
     public function user() {
        return $this->belongsTo(User::class);
    }

    public function option() {
        return $this->belongsTo(FormOption::class, 'option_id');
    }
}
