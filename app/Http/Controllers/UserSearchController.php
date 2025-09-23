<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache as FacadesCache;

class UserSearchController extends Controller
{



    public function index(Request $request)
    {

        $keyword = $request->input('keyword');
        $tenant_id = $request->input('tenant_id');


        $cache_key = 'user:tanent' . $tenant_id . $keyword;


        $users = FacadesCache::remember($cache_key, 300, function ()  use ($keyword, $tenant_id) {

            return User::with('formData.option')   // here I use eager loading for fetching relations upfront 
                // instead of multiple queries  
                ->where('tenant_id', $tenant_id)  // fetch first the users with given tenant id , then....
                ->whereHas('formData.option', function ($query) use ($keyword) {  // ...add a relationship count or exists condition to the 
                    // query with where clauses. in conjunction 
                    // with the MATCH(label) against given keyword condition 
                    $query->whereRaw("MATCH(label) AGAINST(? IN NATURAL LANGUAGE MODE)", [$keyword]);  // I added fulltext index on label column in form_options table 
                    // works like an inverted index ex word => [row1 , row2 , row3 ]
                })->orderBy("id")  // I order the results by Id 
                ->paginate(50); // paginate to return 50 results per page
        });
        // I would like to recommend the meilisearch using laravel scout package for supporting fulltext search .
        //  for denormalizing data we can directly store labels in users table in seprate column and add full text index on it , 
        // with tradeoff of xtra storage
        // lastly we can cache the data for perticular time using Cache facade remember method


        return response()->json(["users" => $users]);

    }
}
