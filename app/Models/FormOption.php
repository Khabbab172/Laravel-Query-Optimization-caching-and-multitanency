<?php

namespace App\Models;

use App\Models\Traits\Tenanted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormOption extends Model
{
    use HasFactory , Tenanted;

    protected $fillable = ['label'] ;
    public function formData() {
        return $this->hasMany(FormData::class);
    }
}
