<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user(); // get authenticated user
        //  adding where clause for identifiying tenant data in whole 
        // later in Tenanted trait we add this scope globally 
        if ($user && $user->tenant_id) {
            $builder->where("tenant_id", $user->tenant_id); 
        }
    }
}
