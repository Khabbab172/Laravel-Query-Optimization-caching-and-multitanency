<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait Tenanted
{
    protected static function bootTenanted(){
        static::creating(function ($model) {
            $tenant_id = Auth::user()->tenant_id ?? null;

            if(is_null($model->tenant_id)){
                $model->tenant_id = $tenant_id;
            }

        });

        static::addGlobalScope(new TenantScope) ;
    }
}
