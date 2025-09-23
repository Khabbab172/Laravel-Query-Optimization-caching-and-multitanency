<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait Tenanted
{
    //  The booting method of trait automatically called when eloquent model boots
    protected static function bootTenanted(){
        // Set the tenant_id before model gets created
        static::creating(function ($model) {
            $tenant_id = Auth::user()->tenant_id ?? null;

            // if a model does not already have tenant id we set it manually to avoid overwritting manually set id
            if(is_null($model->tenant_id)){
                $model->tenant_id = $tenant_id;
            }

        });

        // applying the global scope for all models , this add a where clause for tenant_id 
        //  so from each table it access only concerned tenant data  

        static::addGlobalScope(new TenantScope) ;
    }
}
